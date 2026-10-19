package main

import (
	"context"
	"flag"
	"fmt"
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
	"afisha/internal/collector/jsonld"
	"afisha/internal/fetch"
	"afisha/internal/notify"
	"afisha/internal/runner"
	sched "afisha/internal/schedule"
	"afisha/internal/store"
)

// Один токен для User-Agent и для поиска своих правил в robots.txt.
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

func main() {
	once := flag.Bool("once", false, "один прогон сборщиков и выход")
	notifyTest := flag.Bool("notify-test", false, "отправить проверочное сообщение в Telegram и выйти")
	flag.Parse()

	logger := slog.New(slog.NewTextHandler(os.Stdout, nil))

	ctx, stop := signal.NotifyContext(context.Background(), syscall.SIGINT, syscall.SIGTERM)
	defer stop()

	pool, err := pgxpool.New(ctx, databaseURL())
	if err != nil {
		log.Fatal(err)
	}
	defer pool.Close()

	ua := userAgentToken + "/0.1"
	if c := os.Getenv("COLLECTOR_CONTACT"); c != "" {
		ua += " (" + c + ")"
	}
	client := fetch.New(ua, 2*time.Second)

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

	st := store.New(pool)
	r := &runner.Runner{
		Store:  st,
		Custom: map[string]collector.Collector{},
		Factory: func(src store.SourceRow, cityName string) (collector.Collector, error) {
			cfg, err := jsonld.ParseConfig([]byte(src.Config))
			if err != nil {
				return nil, err
			}
			return jsonld.New(src.Slug, src.Name, src.URL, cityName, cfg, client, userAgentToken, logger), nil
		},
		Notifier: telegram,
		Log:      logger,
		Loc:      minsk,
	}

	if *once {
		r.RunOnce(ctx)
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
	s.Loop(ctx, 30*time.Second, func(err error) { logger.Error("планировщик", "err", err) })
}
