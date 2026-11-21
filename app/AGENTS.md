# AGENTS.md

## Workflow with the user

- For any user request that requires action, first present a concise plan
  in Russian describing what needs to be done. This applies to investigations,
  checks, edits, deployments, and other actions. Wait for the user's explicit
  approval of the plan before starting the requested work.
- Publish all repository changes through GitHub pull requests targeting `main`.
  Create a separate branch for each change, push that branch, and open a pull
  request for review. Do not push changes directly to `main`.

This is a Symfony project. Check `composer.json` for the exact Symfony/PHP version
in use, and read `symfony.lock` to see which recipes ran. Don't assume Doctrine,
Twig, API Platform, Messenger, or Lock are installed unless one of those says so.

## Ask before generating

If the task doesn't specify, ask rather than guess:

- Persistence: Doctrine ORM, Doctrine ODM, or none?
- Interface: server-rendered (Twig), API (Serializer, maybe API Platform), or both?
- Auth: SecurityBundle, and which authenticator?

If you can't ask (no interactive channel), state the assumption you're making and
pick the smallest option (e.g. no persistence layer) rather than scaffolding a
full stack nobody asked for.

## Adding features: Flex, not hand-wiring

Install new capabilities with `composer require <package>` (e.g. `symfony/lock`,
`symfony/messenger`, `orm-pack`) and let the Flex recipe register the bundle and
generate its config. Don't hand-edit `config/bundles.php` or hand-write a bundle's
base config; that's what the recipe is for. Don't skip a good-fit component just
because it isn't installed yet; installing it is one command.

## Conventions

Follow https://symfony.com/doc/current/best_practices.html to write idiomatic
Symfony:

- Use PHP attributes for framework metadata, and not only on controllers:
  `#[Route]`, `#[MapRequestPayload]`, `#[IsGranted]` on actions, `#[Assert\...]`
  on properties, `#[AsCommand]`, `#[AsEventListener]`, `#[AsMessageHandler]`, and
  `#[AsAlias]` / `#[AsTaggedItem]` / `#[Autoconfigure]` on services. No YAML or
  XML routing.
- Rely on autowiring and autoconfiguration. Type-hint constructor arguments and
  let the container resolve them. Where a type-hint can't express it, stay in the
  class with `#[Autowire]` (parameters, env vars, expressions) or `#[Target]` (one
  of several implementations of an interface). A YAML service definition is the
  last resort, not the first.
- Controllers extend `AbstractController`, stay thin, and delegate to services.
- Use the framework for what it already does: Form for server-rendered forms,
  Validator for validation, Serializer for JSON, Messenger for async work,
  Security (voters, authenticators) for access control, Twig `path()`/`url()`
  instead of hardcoded URLs.
- Before hand-writing infrastructure (locks, queues, caches, HTTP clients,
  mailers, schedulers) or reaching for a third-party library, check whether a
  Symfony component covers it. It usually does.

Three specifics worth spelling out, because they are easy to get wrong:

- Bind request data with `#[MapRequestPayload]` / `#[MapQueryString]` on action
  arguments, which wires up Serializer and Validator for you, instead of calling
  `json_decode()` or `SerializerInterface` by hand. If neither package is
  installed yet, `composer require` them rather than falling back to manual
  parsing.
- Use constructor property promotion, and `readonly` for DTOs and value objects.
  Don't mark a service `readonly` if it might become `lazy: true`: a lazy proxy
  can't extend a `readonly` class.
- Use `symfony/lock` (`LockFactory`) for mutual exclusion. A hand-built flag or
  lock file looks fine in review and is usually wrong under concurrency.

## Everyday workflow

- Run the app with `symfony serve -d`, and commands with `symfony console ...`
  (or `bin/console` when the Symfony CLI isn't available).
- When something fails, read `var/log/dev.log` and the web profiler
  (`/_profiler`) before changing code.
- If `maker-bundle` is installed, prefer `bin/console make:*` with every argument
  passed up front and `--no-interaction` where supported: makers prompt on a
  terminal by default, which hangs a non-interactive shell. If a maker still
  needs interactive input, hand-write the code instead.
- If Doctrine ORM is installed, schema changes go through migrations
  (`bin/console make:migration`, then `doctrine:migrations:migrate`), never
  `doctrine:schema:update` or hand-written SQL.
- `.env` is committed and holds defaults only. Real secrets belong in `.env.local`
  (git-ignored) or the secrets vault (`bin/console secrets:set`), read via
  `%env(...)%`.

## Testing

Install `symfony/test-pack` if it isn't already. Functional/HTTP tests extend
`WebTestCase`; service-level tests extend `KernelTestCase`. Run
`php bin/phpunit` (falls back to `vendor/bin/phpunit`). A feature isn't done
until it has a test that exercises it the way a caller would, an HTTP request for
a controller or a service call for a service, not just "it didn't throw."

## Code style

Symfony's coding standard, the `@Symfony` php-cs-fixer ruleset (a PSR-12-derived
superset). Run `vendor/bin/php-cs-fixer fix` if `friendsofphp/php-cs-fixer` is
installed; it isn't part of the skeleton by default.

## Discover, don't guess

Framework APIs change between versions and your training data may be stale. Look
things up in the project instead of relying on memory:

- `bin/console about`: versions, environment, paths.
- `bin/console debug:router`, `debug:container`, `debug:autowiring <name>`,
  `debug:config <bundle>`, `config:dump-reference <bundle>`: what exists and how
  it is configured.
- `bin/console lint:container`, plus `lint:twig templates/` and
  `lint:yaml config/` where those packages are installed: validate before running.
- Read the installed source and docblocks under `vendor/`.
- Docs: https://symfony.com/doc/current/ (switch to the version matching
  `composer.json` if it differs).

## Events and calendar

- The homepage shows one card per event on each Minsk calendar day with an
  upcoming, non-cancelled session. When several sessions fall on the same day,
  the card uses the first upcoming session and its ticket link. All cards for
  the same event link to its detail page, which shows the full schedule.
- The homepage defaults to the nearest weekend. The «Все даты» filter shows
  upcoming events across all dates and supports pagination.
- Before marking a card as a duplicate, match the title (ignoring case and
  extra whitespace), venue, start date and start time for every non-cancelled
  session on that card. A missing venue cannot establish a match. Use the same
  rule for publication, moderator selection and the batch command.
- Marking a duplicate keeps both schedules intact. If the collector imports an
  unmatched session into a linked duplicate, it restores that card to draft
  for review. Existing duplicate statuses are preserved during deployment.

## Operations and monitoring

- `/admin/operations` shows current PostgreSQL and import checks, the latest
  external monitor report, and up to 100 recent entries per PHP and Go journal.
  `/admin/sql-queries` shows recent Doctrine SQL templates, execution times, and
  results. Both pages require `ROLE_ADMIN`. The SQL journal masks literals and
  omits bound values. SQL entries are displayed on the SQL page.
- PHP and Go journals and the latest external report live in the shared Docker
  volume `app_logs`. Journals rotate daily and at 5 MiB, with up to seven backups
  per journal. Caddy expires backups after seven days at rotation. Go checks for
  expired files at startup and hourly. SQL shares the PHP retention policy.
- The Cloudflare Worker in `docker/monitor/worker.mjs` checks the public site,
  `/healthz`, and `/healthz/import` every five minutes. It publishes to
  `POST /healthz/report` using `AFISHA_MONITOR_REPORT_TOKEN`, which must match on
  the app and Worker. Reports become stale after 15 minutes. See
  [Monitoring in DEPLOY.md](../DEPLOY.md#мониторинг) for configuration.
- Production release `2026-11-20-calendar` includes the operations pages, the
  per-day calendar and the exact duplicate rule. The deployed Compose files
  are in `/opt/afisha-rb/releases/2026-11-20-calendar` and use the production
  environment file at `/opt/afisha-rb/.env.production`.
- During the 2026-11-20 IP migration, the server acquired its new public
  address at 17:24 Minsk time and restarted again at 17:27. HTTPS and both
  public health checks passed on the new address after the migration.
- At the post-migration check, production had no external report token or
  saved external report. Telegram alerts are independent of the report token.
  Use Cloudflare execution logs and Telegram delivery results to confirm
  alert delivery.
