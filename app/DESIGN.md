# Дизайн MVP: afisha_rb

Дополняет [PLAN.md](PLAN.md). Новое относительно плана: расписания событий (сеансы), роли в админке, Telegram-бот с персональными подписками. Карта, форма «предложить событие» и публичный канал в MVP не входят.

## 1. Архитектура

```
 источники (сайты)
       │  HTTP
       ▼
┌─────────────┐  INSERT   ┌──────────────────────────────┐
│ Go parser   │──────────▶│ PostgreSQL                   │
│ (app-go)    │           │  import_event (staging)      │
└─────────────┘           │  event, event_session, ...   │
                          └──────────────┬───────────────┘
                                         │ Doctrine
              ┌──────────────────────────┴──────────────────────┐
              │ Symfony 8.1 (FrankenPHP)                        │
              │  • сайт: Twig + Turbo/Stimulus                  │
              │  • EasyAdmin: события, категории, модерация     │
              │  • Import: staging → event (дедуп, маппинг)     │
              │  • Telegram: webhook бота                       │
              │  • Scheduler + Messenger worker: рассылки       │
              └──────────────────────────┬──────────────────────┘
                                         │ Bot API
                                         ▼
                                    Telegram
```

Стек остаётся текущим: Symfony 8.1, Doctrine ORM 3 с XML-маппингом, EasyAdmin 5, PostgreSQL, FrankenPHP, Docker, asset-mapper. Добавить через `composer require`: `symfony/scheduler`, `symfony/http-client`, `symfony/security-bundle`, `symfony/rate-limiter`, `symfony/lock`, Turbo/Stimulus из UX.

Код модулей по существующей схеме (Domain / Application с командами и запросами / Infrastructure / контроллеры):

| Модуль | Содержимое |
|---|---|
| `Catalog` (есть) | категории, города, площадки, события, сеансы, публичные страницы |
| `Import` | приём из staging, дедупликация, маппинг категорий источников, модерация |
| `Identity` | пользователи админки, роли, вход |
| `Telegram` | webhook, диалог настройки, подписки, рассылка |
| `Admin` (есть) | контроллеры EasyAdmin |

Почему парсер пишет в staging, а не в `event`: правила дедупа, привязки к площадкам и категориям живут в одном месте (PHP), парсер остаётся «глупым» и заменяемым. Для Go в Postgres отдельная роль с правом только на `import_event`, `source`, `parser_run`.

## 2. Модель данных

Уже есть: `category(id, name, alias, position, available, created_at)`.

**Справочники**
- `city(id, name, slug, available)`: Минск первым, остальные включаются флагом.
- `category`: добавить `parent_id` (nullable) для подкатегорий, например «Танцы» → «Сальса». В MVP достаточно одного уровня.
- `venue(id, city_id, name, address, lat, lng)`: lat/lng nullable, заполняются позже.

**События и расписание**
- `event(id, title, slug, description, category_id, city_id, venue_id, price_min, price_max, is_free, age_limit, image_url, source_url, ticket_url, status, source_id, external_id, created_by, created_at, updated_at)`
  - `status`: `draft | published | rejected | archived`
  - уникальный индекс `(source_id, external_id)` там, где они заданы
- `event_session(id, event_id, starts_at timestamptz, ends_at, venue_id nullable, ticket_url nullable, status)`: одна строка = один показ/занятие.
  - `status`: `scheduled | cancelled | sold_out`
  - уникальный индекс `(event_id, starts_at, venue_id)`, индекс по `starts_at`
  - одно событие с несколькими датами = одна карточка и N сеансов; фильтры по датам и рассылка работают именно по сеансам

**Импорт**
- `source(id, code, name, base_url, city_id, enabled, auto_publish, last_run_at, last_status)`
- `source_category_map(source_id, source_category, category_id)`: перевод рубрик источника в наши.
- `import_event(id, source_id, external_id, payload jsonb, sessions jsonb, fingerprint, state, event_id nullable, created_at)`
  - `state`: `new | merged | needs_review | failed`
  - `fingerprint` = хэш нормализованных названия + даты + площадки
- `parser_run(id, source_id, started_at, finished_at, found, created, updated, errors, status)`: по ней алерт «источник вернул 0».

**Пользователи и бот**
- `user(id, email, password, roles json, active, created_at)`
- `tg_subscriber(id, chat_id unique, username, city_id, active, created_at)`
- `subscription(id, subscriber_id, horizon_days, frequency, weekday nullable, send_at time, only_free, active, last_sent_at)`
- `subscription_category(subscription_id, category_id)`
- `digest_log(subscription_id, period_key, sent_at)`: уникальность `(subscription_id, period_key)` защищает от повторной отправки.

## 3. Роли

| Роль | Может |
|---|---|
| `ROLE_EDITOR` | создавать и править свои события и сеансы; отправлять на публикацию |
| `ROLE_MODERATOR` (включает editor) | очередь импорта, публикация/отклонение/слияние любых событий, площадки |
| `ROLE_ADMIN` (включает moderator) | категории, города, источники, пользователи и их роли, статистика парсера |

Реализация: `role_hierarchy`, `access_control: ^/admin`, form login, голосователь `EventVoter` («редактор правит только свои»), в EasyAdmin доступ к CRUD через `setPermission()`. Публичный сайт без регистрации; пользователь в MVP = только персонал.

## 4. Парсер (Go, `app-go/`)

```
app-go/
  cmd/parser/main.go
  internal/
    source/        интерфейс Source + по пакету на источник
    fetch/         HTTP-клиент: User-Agent, rate limit, retry, кэш
    store/         pgx: запись import_event, parser_run
    schedule/      запуск раз в сутки (cron внутри процесса)
  testdata/        сохранённые страницы для тестов
```

- Интерфейс: `Fetch(ctx) ([]RawEvent, error)`; `RawEvent` содержит внешний id, название, категорию источника, площадку и адрес, список сеансов, цену, ссылки.
- Приоритет способов получения: API/RSS → JSON-LD `schema.org/Event` → HTML-вёрстка.
- Идемпотентность: `INSERT ... ON CONFLICT (source_id, external_id) DO UPDATE`. Повторный запуск не создаёт дублей.
- Берём только факты (название, даты, место, цена, ссылка), без копирования текстов и фото целиком.
- Горизонт: сегодня + 60 дней. Города: сначала Минск, остальные добавляются новой записью в `source` и новым пакетом источника.
- Источники для Минска нужно выбрать на этапе исследования (афиши театров, кинотеатров, билетных операторов, клубов, студий). Список-кандидат не фиксирую, пока не проверено, у кого есть API или разметка, и что разрешают условия использования.

Обработка в Symfony (`app:import:process`, по расписанию после запуска парсера):
1. Берём `import_event` в состоянии `new`.
2. Маппим категорию через `source_category_map`; нет маппинга → `needs_review`.
3. Подбираем площадку по названию/адресу или создаём новую.
4. Ищем дубль по `fingerprint` среди `event`; найден → дополняем сеансами, иначе создаём `event`.
5. `source.auto_publish = true` → `published`, иначе `draft` в очередь модерации.

## 5. Сайт

Маршруты:
- `/` главная: ближайшие события, список категорий
- `/{city}` события города
- `/{city}/{category}` страница категории
- `/event/{slug}` карточка: описание, площадка, цена, список сеансов, кнопка «Билеты/Источник»

Фильтры (query-параметры через `#[MapQueryString]`): `when=today|tomorrow|weekend|week`, `from`, `to`, `free=1`, `sub=` (подкатегория), `q`. Выборка идёт по `event_session` (`starts_at >= now()`), группируется по событию, показывается ближайший сеанс. Фильтры применяются через Turbo Frame без перезагрузки; URL остаётся ссылкой, которую можно сохранить. Прошедшие события и события без будущих сеансов не показываются.

SEO в MVP: понятные URL, title/description, `sitemap.xml`, JSON-LD `Event`.

## 6. Telegram-бот

Реализация внутри Symfony: webhook-контроллер → Messenger → обработчик. Библиотеки не нужны, хватит HTTP-клиента к Bot API.

Диалог:
1. `/start`: выбор города (инлайн-кнопки).
2. Выбор категорий, мультивыбор с галочками и кнопка «Готово».
3. Горизонт: 7 / 14 / 30 дней.
4. Периодичность: раз в день / раз в неделю (день недели) и время.
5. Дополнительно: «только бесплатные».
6. `/settings` меняет любой пункт, `/stop` отключает, `/now` присылает дайджест немедленно.

Рассылка:
- `symfony/scheduler` каждые 15 минут выбирает подписки, у которых наступило время и нет записи в `digest_log` за текущий период.
- На каждую подписку ставится сообщение в Messenger (транспорт `doctrine://`), worker отправляет и пишет `digest_log`.
- Содержимое: сеансы выбранных категорий в окне `[сейчас, сейчас + horizon_days]`, сгруппированы по дате: время, название, площадка, цена, ссылка на карточку на сайте. Лимит сообщения Telegram 4096 символов, длинный дайджест режется на части; в конце ссылка на сайт с теми же фильтрами.
- Пустой дайджест не отправляется.
- Ограничения Bot API: не более ~30 сообщений в секунду; ответ 403 → подписчик `active = false`.

## 7. Нефункциональное для MVP

- Бэкап Postgres раз в сутки; логи парсера в `parser_run`.
- Алерт администратору в Telegram: источник вернул 0 событий, упала рассылка.
- Тесты: парсеры на сохранённых страницах, дедуп, фильтры сайта, подбор сеансов для дайджеста.
- Часовой пояс: хранить `timestamptz`, показывать `Europe/Minsk`.

## 8. Этапы и оценка

Прежний план (~116 ч) плюс новые части.

| Этап | Содержание | Оценка |
|---|---|---|
| 1 | Модель данных и миграции, город/площадка/событие/сеанс, Identity и роли, EasyAdmin для событий и сеансов | 25–30 ч |
| 2 | Исследование источников; Go-парсер, 2–3 источника Минска; staging, импорт, дедуп, очередь модерации | 35–40 ч |
| 3 | Публичный сайт: главная, город, категории, карточка, фильтры, вёрстка | 35 ч |
| 4 | Telegram-бот: диалог, подписки, рассылка, логирование | 20–25 ч |
| 5 | SEO, бэкапы, алерты, второй город, обкатка | 12–15 ч |

Итого около 130–145 ч, то есть ориентировочно 12–14 недель при 11 ч в неделю. Если срок жмёт, режем в порядке: 1) подкатегории и поиск `q`; 2) третий источник; 3) режим «раз в неделю» (оставить только ежедневный дайджест); 4) JSON-LD.

## 9. Открытые вопросы

1. Какие источники Минска берём первыми и разрешают ли они сбор (условия использования, robots.txt)?
2. Парсер ходит напрямую в Postgres (как здесь) или предпочтительнее внутренний HTTP-эндпоинт на Symfony?
3. Админы и редакторы — кто они: только вы, или будут внешние люди (тогда нужны приглашения и сброс пароля)?
4. Нужен ли публичный канал в Telegram помимо бота подписок?
5. Где хостим: Hetzner или хостинг в РБ (влияет на требования по хранению данных подписчиков)?
