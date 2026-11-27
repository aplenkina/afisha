package main

import (
	"context"
	"flag"
	"fmt"
	"io"
	"log"
	"log/slog"
	"net/http"
	"net/url"
	"os"
	"os/signal"
	"strings"
	"syscall"
	"time"
	_ "time/tzdata"

	"github.com/jackc/pgx/v5/pgxpool"

	"afisha/internal/collector"
	"afisha/internal/collector/citydog"
	"afisha/internal/collector/jsonld"
	"afisha/internal/fetch"
	"afisha/internal/notify"
	"afisha/internal/runner"
	sched "afisha/internal/schedule"
	"afisha/internal/store"
)

// userAgentToken — имя бота для User-Agent и для поиска своих правил в robots.txt.
const userAgentToken = "AfishaRBBot"

func envOrFile(name string) string {
	if path := os.Getenv(name + "_FILE"); path != "" {
		data, err := os.ReadFile(path)
		if err != nil {
			log.Fatal(err)
		}
		return strings.TrimSpace(string(data))
	}
	return os.Getenv(name)
}

func databaseURL() string {
	u := url.URL{
		Scheme: "postgres",
		User:   url.UserPassword(envOrFile("DATABASE_USER"), envOrFile("DATABASE_PASSWORD")),
		Host:   fmt.Sprintf("%s:%s", os.Getenv("DATABASE_HOST"), os.Getenv("DATABASE_PORT")),
		Path:   os.Getenv("DATABASE_NAME"),
	}

	return u.String()
}

type bezKassiraDraftCollector struct{ *jsonld.BezKassira }

func (c bezKassiraDraftCollector) Collect(ctx context.Context) (collector.Result, error) {
	result, err := c.CollectRecent(ctx)
	for _, event := range result.Events {
		event.Observation.DraftsOnly = true
	}
	return result, err
}

func main() {
	once := flag.Bool("once", false, "один прогон сборщиков и выход")
	source := flag.String("source", "", "код источника для прогона с -once")
	draftsOnly := flag.Bool("drafts-only", false, "перепроверить только существующие черновики BezKassira")
	publishBez := flag.Bool("publish-bezkassira", false, "сводка по автопубликации черновиков BezKassira")
	apply := flag.Bool("apply", false, "сохранить статусы и включить автопубликацию BezKassira")
	notifyTest := flag.Bool("notify-test", false, "отправить проверочное сообщение в Telegram и выйти")
	flag.Parse()
	if (*apply && !*publishBez) || (*publishBez && (*once || *notifyTest)) {
		log.Fatal("-apply используется только с -publish-bezkassira, без -once и -notify-test")
	}
	if *draftsOnly && (!*once || *source != "bezkassira") {
		log.Fatal("-drafts-only используется только с -once -source bezkassira")
	}
	if *source != "" && (!*once || *notifyTest) {
		log.Fatal("-source используется только с -once")
	}

	ctx, stop := signal.NotifyContext(context.Background(), syscall.SIGINT, syscall.SIGTERM)
	defer stop()

	var output io.Writer = os.Stdout
	if path := os.Getenv("COLLECTOR_LOG_PATH"); path != "" {
		journal := logFile{path: path, maxBytes: 5 * 1024 * 1024, console: os.Stderr}
		log.SetOutput(journal)
		journal.console = os.Stdout
		output = journal
		if !*once && !*notifyTest {
			go journal.maintain(ctx)
		}
	}
	logger := slog.New(slog.NewTextHandler(output, nil))

	pool, err := pgxpool.New(ctx, databaseURL())
	if err != nil {
		log.Fatal(err)
	}
	defer pool.Close()

	st := store.New(pool)
	if *publishBez {
		result, err := st.PublishBezKassiraDrafts(ctx, *apply)
		if err != nil {
			log.Fatalf("проверка автопубликации: %v", err)
		}
		fmt.Printf("публикация: %d; дубли: %d; проверка: %d; конфликты источников: %d; применено: %t\n",
			result.Published, result.Duplicate, result.Review, result.Conflict, *apply)
		return
	}

	// Честный User-Agent с контактом: COLLECTOR_CONTACT=почта или адрес сайта.
	ua := userAgentToken + "/0.1"
	if c := os.Getenv("COLLECTOR_CONTACT"); c != "" {
		ua += " (" + c + ")"
	}
	client := fetch.New(ua, 2*time.Second)
	bezClient := fetch.New(ua, 750*time.Millisecond)

	telegram := notify.NewTelegram(envOrFile("TELEGRAM_BOT_TOKEN"), envOrFile("TELEGRAM_CHAT_ID"), logger)
	if *notifyTest {
		if err := telegram.Test(ctx); err != nil {
			log.Fatalf("проверка Telegram не прошла: %v", err)
		}
		fmt.Println("Проверочное сообщение отправлено")
		return
	}

	minsk, err := time.LoadLocation("Europe/Minsk")
	if err != nil {
		log.Fatal(err)
	}

	r := &runner.Runner{
		Store:  st,
		Custom: map[string]collector.Collector{}, // сюда регистрируются сборщики под конкретный сайт
		Factory: func(src store.SourceRow, cityName string) (collector.Collector, error) {
			if src.Slug == "citydog" {
				return citydog.New(src.Name, cityName, src.URL, userAgentToken, client, minsk, logger)
			}
			cfg, err := jsonld.ParseConfig([]byte(src.Config))
			if err != nil {
				return nil, err
			}
			if src.Slug == "bezkassira" {
				bez := jsonld.NewBezKassira(src.Name, src.URL, cityName, cfg, bezClient, userAgentToken, logger,
					func(ctx context.Context, recent bool) ([]string, error) {
						if *draftsOnly {
							return st.DraftBezKassiraURLs(ctx, src.ID)
						}
						return st.KnownEventURLs(ctx, src.ID, recent)
					})
				if *draftsOnly {
					return bezKassiraDraftCollector{bez}, nil
				}
				return bez, nil
			}
			return jsonld.New(src.Slug, src.Name, src.URL, cityName, cfg, client, userAgentToken, logger), nil
		},
		Notifier: telegram,
		Log:      logger,
		Loc:      minsk,
	}

	if *once {
		if *source == "" {
			r.RunOnce(ctx)
		} else if !r.RunSourceOnce(ctx, *source) {
			log.Fatal("сбор источника не завершился успешно")
		}
		return
	}

	go serveHealth(pool)
	schedule(ctx, r, st, logger)
}

func serveHealth(pool *pgxpool.Pool) {
	http.HandleFunc("/health", func(w http.ResponseWriter, req *http.Request) {
		if err := pool.Ping(req.Context()); err != nil {
			http.Error(w, err.Error(), http.StatusServiceUnavailable)
			return
		}
		fmt.Fprint(w, "ok")
	})
	log.Fatal(http.ListenAndServe(":8080", nil))
}

// schedule запускает сборщики раз в сутки в COLLECT_AT (по Минску, по умолчанию 04:00) по настенным часам.
// Пропущенный запуск (контейнер был выключен или спал) догоняется, перезапуск после сбора ничего не запускает.
// COLLECT_ON_START=1 — ещё и сразу при старте.
func schedule(ctx context.Context, r *runner.Runner, st *store.Store, logger *slog.Logger) {
	loc, err := time.LoadLocation("Europe/Minsk")
	if err != nil {
		log.Fatal(err)
	}
	at := os.Getenv("COLLECT_AT")
	if at == "" {
		at = "04:00"
	}
	clock, err := time.Parse("15:04", at)
	if err != nil {
		log.Fatalf("COLLECT_AT должен быть в формате ЧЧ:ММ: %v", err)
	}

	s := &sched.Schedule{
		Hour: clock.Hour(), Minute: clock.Minute(), Loc: loc,
		LastRun: st.LastRunAt,
		Run: func(ctx context.Context) {
			logger.Info("запуск по расписанию")
			r.RunOnce(ctx)
		},
	}

	if os.Getenv("COLLECT_ON_START") == "1" {
		r.RunOnce(ctx)
		s.MarkRan(time.Now())
	}

	logger.Info("планировщик запущен", "daily_at", at, "zone", loc.String())
	ticker := time.NewTicker(30 * time.Second)
	defer ticker.Stop()
	const refreshInterval = 3 * time.Hour
	nextRefresh := time.Now().Truncate(refreshInterval).Add(refreshInterval)
	for {
		ran, err := s.Tick(ctx)
		if err != nil {
			logger.Error("планировщик", "err", err)
		}
		now := time.Now()
		if !now.Before(nextRefresh) {
			nextRefresh = now.Truncate(refreshInterval).Add(refreshInterval)
			if !ran {
				r.RefreshRecent(ctx)
			}
		}
		select {
		case <-ticker.C:
		case <-ctx.Done():
			return
		}
	}
}
