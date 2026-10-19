package runner

import (
	"context"
	"errors"
	"io"
	"log/slog"
	"strings"
	"testing"
	"time"

	"afisha/internal/collector"
	"afisha/internal/store"
)

func TestMatchCity(t *testing.T) {
	cities := map[string]int{"минск": 5, "могилев": 9}

	cases := []struct {
		name  string
		hints []string
		want  int
		ok    bool
	}{
		{"первая подсказка", []string{"Минск", "Брест"}, 5, true},
		{"вторая, если первая неизвестна", []string{".", "Минск"}, 5, true},
		{"ё и регистр", []string{"МОГИЛЁВ"}, 9, true},
		{"город не из базы", []string{"Лондон"}, 0, false},
		{"подсказок нет", nil, 0, false},
	}
	for _, c := range cases {
		t.Run(c.name, func(t *testing.T) {
			got, ok := matchCity(cities, c.hints)
			if got != c.want || ok != c.ok {
				t.Errorf("matchCity(%v) = %d, %v; want %d, %v", c.hints, got, ok, c.want, c.ok)
			}
		})
	}
}

type recordedRun struct{ status, summary string }

type fakeStore struct {
	available bool
	config    string
	extra     []store.SourceRow
	saved     []collector.Event
	runs      []recordedRun
	created   map[string]bool // ExternalID -> уже есть в базе
}

func (f *fakeStore) Cities(context.Context) (map[string]int, error) {
	return map[string]int{"минск": 5}, nil
}

func (f *fakeStore) Categories(context.Context) (map[string]int, error) {
	return map[string]int{"concert": 11, "sport": 12}, nil
}

func (f *fakeStore) CategoryNames(context.Context) (map[string]string, error) {
	return map[string]string{"concert": "Концерты", "sport": "Спорт"}, nil
}

func (f *fakeStore) Sources(context.Context) ([]store.SourceRow, error) {
	main := store.SourceRow{ID: 1, CityID: 5, Name: "Fake", Slug: "fake", URL: "https://fake.by", Available: f.available, Config: f.config}
	return append([]store.SourceRow{main}, f.extra...), nil
}

func (f *fakeStore) Save(_ context.Context, _, _, _ int, e collector.Event) (bool, error) {
	f.saved = append(f.saved, e)
	return !f.created[e.ExternalID], nil
}

func (f *fakeStore) RecordRun(_ context.Context, _ int, status, summary string) error {
	f.runs = append(f.runs, recordedRun{status, summary})
	return nil
}

type fakeCollector struct {
	res   collector.Result
	err   error
	calls int
}

func (c *fakeCollector) Slug() string    { return "fake" }
func (c *fakeCollector) Name() string    { return "Fake" }
func (c *fakeCollector) BaseURL() string { return "https://fake.by" }
func (c *fakeCollector) Collect(context.Context) (collector.Result, error) {
	c.calls++
	return c.res, c.err
}

func withEvents(events ...collector.Event) *fakeCollector {
	return &fakeCollector{res: collector.Result{Events: events}}
}

type fakeNotifier struct{ messages []string }

func (n *fakeNotifier) Send(_ context.Context, text string) { n.messages = append(n.messages, text) }

func newRunner(st *fakeStore, c *fakeCollector) (*Runner, *fakeNotifier) {
	n := &fakeNotifier{}
	return &Runner{
		Store:    st,
		Custom:   map[string]collector.Collector{"fake": c},
		Notifier: n,
		Log:      slog.New(slog.NewTextHandler(io.Discard, nil)),
		Loc:      time.FixedZone("Minsk", 3*3600),
	}, n
}

func event(id, category string, hints ...string) collector.Event {
	return collector.Event{
		ExternalID: id, Title: id, CategoryAlias: category, CityHints: hints,
		VenueName: "Клуб", StartsAt: time.Date(2026, 10, 21, 16, 0, 0, 0, time.UTC),
	}
}

func oneMessage(t *testing.T, n *fakeNotifier) string {
	t.Helper()
	if len(n.messages) != 1 {
		t.Fatalf("ожидали одно сообщение, получили %d: %q", len(n.messages), n.messages)
	}
	return n.messages[0]
}

func assertOneRun(t *testing.T, st *fakeStore, status, contains string) {
	t.Helper()
	if len(st.runs) != 1 {
		t.Fatalf("ожидали одну запись итога, получили %d: %+v", len(st.runs), st.runs)
	}
	if st.runs[0].status != status || !strings.Contains(st.runs[0].summary, contains) {
		t.Errorf("итог = %+v, ожидали статус %q и %q в сводке", st.runs[0], status, contains)
	}
}

func contains(t *testing.T, text string, parts ...string) {
	t.Helper()
	for _, p := range parts {
		if !strings.Contains(text, p) {
			t.Errorf("в сообщении нет %q:\n%s", p, text)
		}
	}
}

func TestSuccessfulRunSendsFullSummary(t *testing.T) {
	st := &fakeStore{available: true, created: map[string]bool{"old": true}}
	c := withEvents(
		event("new-concert", "concert", "Минск"),
		event("new-sport", "sport", "Минск"),
		event("old", "concert", "Минск"),
		event("nocity", "concert", "Лондон"),
		event("nocat", "unknown", "Минск"),
	)
	c.res.Stats = collector.Stats{PagesTotal: 5, PagesLoaded: 5, Past: 8, NoCategory: 30, Broken: 1}
	r, n := newRunner(st, c)

	r.RunOnce(context.Background())

	msg := oneMessage(t, n)
	contains(t, msg,
		"✅ Сбор событий завершён",
		"Fake — ✅ успешно",
		"Собрано: 5",
		"Новых: 2 · обновлено: 1",
		"Пропущено при записи: 2 (город не определён: 1, категории нет в базе: 1)",
		"Страницы: загружено 5 из 5",
		"Отброшено на сайте: прошедших — 8, из разделов без категории — 30, нечитаемых блоков — 1",
		"Новые по категориям: Концерты — 1, Спорт — 1",
		"• new-concert — 21.10 19:00, Минск, Клуб", // 16:00 UTC = 19:00 Минск
	)
	assertOneRun(t, st, StatusOK, "собрано 5, новых 2, обновлено 1, пропущено 2, ошибок 0")
}

func TestSummaryWithoutNewEventsStillReportsCounters(t *testing.T) {
	st := &fakeStore{available: true, created: map[string]bool{"old": true}}
	r, n := newRunner(st, withEvents(event("old", "concert", "Минск")))

	r.RunOnce(context.Background())

	msg := oneMessage(t, n)
	contains(t, msg, "✅", "Новых: 0 · обновлено: 1")
	if strings.Contains(msg, "Новые события:") || strings.Contains(msg, "Новые по категориям") {
		t.Errorf("без новых событий списков быть не должно:\n%s", msg)
	}
}

func TestEmptySourceIsReportedAsWarning(t *testing.T) {
	st := &fakeStore{available: true}
	r, n := newRunner(st, withEvents())

	r.RunOnce(context.Background())

	contains(t, oneMessage(t, n), "⚠️ Сбор событий завершён с замечаниями", "источник вернул 0 событий")
	assertOneRun(t, st, StatusEmpty, "0 событий")
}

func TestCollectorErrorIsReported(t *testing.T) {
	st := &fakeStore{available: true}
	c := &fakeCollector{err: errors.New("boom")}
	r, n := newRunner(st, c)

	r.RunOnce(context.Background())

	contains(t, oneMessage(t, n), "⚠️", "❌ сборщик упал: boom")
	assertOneRun(t, st, StatusError, "boom")
}

func TestNothingSavedIsReportedAsError(t *testing.T) {
	st := &fakeStore{available: true}
	r, n := newRunner(st, withEvents(event("x", "concert", "Лондон")))

	r.RunOnce(context.Background())

	contains(t, oneMessage(t, n), "❌ ничего не сохранилось")
	assertOneRun(t, st, StatusError, "ничего не сохранилось")
}

func TestPagesProblemsMakeSummaryWarn(t *testing.T) {
	st := &fakeStore{available: true}
	c := withEvents(event("a", "concert", "Минск"))
	c.res.Stats = collector.Stats{PagesTotal: 5, PagesLoaded: 3, PagesBlocked: 1, PagesFailed: 1}
	r, n := newRunner(st, c)

	r.RunOnce(context.Background())

	contains(t, oneMessage(t, n), "⚠️ Сбор событий завершён с замечаниями", "с замечаниями",
		"загружено 3 из 5 (закрыто в robots.txt: 1, не загрузилось: 1)")
}

func TestDisabledSourceIsNotRunAndNotReported(t *testing.T) {
	st := &fakeStore{available: false}
	c := withEvents(event("a", "concert", "Минск"))
	r, n := newRunner(st, c)

	r.RunOnce(context.Background())

	if c.calls != 0 || len(st.saved) != 0 || len(st.runs) != 0 || len(n.messages) != 0 {
		t.Errorf("выключенный источник не должен ни собираться, ни попадать в сводку: calls=%d saved=%d runs=%d msgs=%q",
			c.calls, len(st.saved), len(st.runs), n.messages)
	}
}

func TestSummaryMentionsSkippedSources(t *testing.T) {
	st := &fakeStore{
		available: true,
		extra: []store.SourceRow{
			{ID: 2, CityID: 5, Name: "Off", Slug: "off", Available: false},
			{ID: 3, CityID: 5, Name: "NoCfg", Slug: "nocfg", Available: true},
		},
	}
	r, n := newRunner(st, withEvents(event("a", "concert", "Минск")))

	r.RunOnce(context.Background())

	contains(t, oneMessage(t, n), "Не запускались: выключено в админке — 1, без настроек сборщика — 1")
}

func TestBuildsCollectorFromConfig(t *testing.T) {
	st := &fakeStore{available: true, config: `{"pages": ["/"]}`}
	built := withEvents(event("a", "concert", "Минск"))

	var gotCity string
	r, n := newRunner(st, &fakeCollector{})
	r.Custom = nil
	r.Factory = func(src store.SourceRow, cityName string) (collector.Collector, error) {
		gotCity = cityName
		return built, nil
	}

	r.RunOnce(context.Background())

	if built.calls != 1 || len(st.saved) != 1 {
		t.Errorf("сборщик из настроек должен отработать: calls=%d saved=%d", built.calls, len(st.saved))
	}
	if gotCity != "минск" {
		t.Errorf("фабрика должна получить город источника, получила %q", gotCity)
	}
	contains(t, oneMessage(t, n), "✅")
}

func TestBadConfigIsReportedAsError(t *testing.T) {
	st := &fakeStore{available: true, config: `{}`}
	r, n := newRunner(st, &fakeCollector{})
	r.Custom = nil
	r.Factory = func(store.SourceRow, string) (collector.Collector, error) {
		return nil, errors.New("в настройках нет pages")
	}

	r.RunOnce(context.Background())

	contains(t, oneMessage(t, n), "❌ некорректные настройки сборщика: в настройках нет pages")
	assertOneRun(t, st, StatusError, "нет pages")
	if len(st.saved) != 0 {
		t.Error("с плохими настройками ничего не сохраняется")
	}
}

func TestSourceWithoutCollectorOrConfigIsSkippedSilently(t *testing.T) {
	st := &fakeStore{available: true}
	r, n := newRunner(st, &fakeCollector{})
	r.Custom = nil

	r.RunOnce(context.Background())

	if len(st.saved) != 0 || len(n.messages) != 0 || len(st.runs) != 0 {
		t.Errorf("источник без сборщика молча пропускается: saved=%d msgs=%q runs=%v", len(st.saved), n.messages, st.runs)
	}
}

func TestCustomCollectorWinsOverConfig(t *testing.T) {
	st := &fakeStore{available: true, config: `{"pages": ["/"]}`}
	custom := withEvents(event("a", "concert", "Минск"))
	r, _ := newRunner(st, custom)
	r.Factory = func(store.SourceRow, string) (collector.Collector, error) {
		t.Fatal("фабрика не должна вызываться, если есть свой сборщик")
		return nil, nil
	}

	r.RunOnce(context.Background())

	if custom.calls != 1 {
		t.Errorf("свой сборщик не вызван: %d", custom.calls)
	}
}

func TestOneSourceFailureDoesNotStopOthers(t *testing.T) {
	st := &fakeStore{
		available: true,
		extra:     []store.SourceRow{{ID: 2, CityID: 5, Name: "Second", Slug: "second", Available: true}},
	}
	broken := &fakeCollector{err: errors.New("boom")}
	second := withEvents(event("b", "concert", "Минск"))
	r, n := newRunner(st, broken)
	r.Custom["second"] = &namedCollector{fakeCollector: second, slug: "second", name: "Second"}

	r.RunOnce(context.Background())

	msg := oneMessage(t, n)
	contains(t, msg, "Fake — ❌", "Second — ✅")
	if second.calls != 1 {
		t.Error("второй источник должен отработать после сбоя первого")
	}
}

type namedCollector struct {
	*fakeCollector
	slug, name string
}

func (c *namedCollector) Slug() string { return c.slug }
func (c *namedCollector) Name() string { return c.name }
