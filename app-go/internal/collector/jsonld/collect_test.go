package jsonld

import (
	"context"
	"fmt"
	"io"
	"log/slog"
	"net/http"
	"net/http/httptest"
	"strings"
	"sync"
	"testing"
	"time"

	"afisha/internal/fetch"
)

func TestParseConfig(t *testing.T) {
	good := `{"pages": ["/", "/concerts/"], "categories": {"concerts": "concert"}}`
	cfg, err := ParseConfig([]byte(good))
	if err != nil || len(cfg.Pages) != 2 || cfg.Categories["concerts"] != "concert" || cfg.CityFallback {
		t.Fatalf("cfg=%+v err=%v", cfg, err)
	}

	bad := map[string]string{
		"не json":           `{`,
		"нет pages":         `{"categories": {"a": "b"}}`,
		"нет categories":    `{"pages": ["/"]}`,
		"чужой хост":        `{"pages": ["https://evil.example/"], "categories": {"a": "b"}}`,
		"протокол-отн. URL": `{"pages": ["//evil.example/"], "categories": {"a": "b"}}`,
		"без слеша":         `{"pages": ["afisha"], "categories": {"a": "b"}}`,
	}
	for name, raw := range bad {
		if _, err := ParseConfig([]byte(raw)); err == nil {
			t.Errorf("%s: ожидали ошибку", name)
		}
	}
}

const eventPage = `<html><script type="application/ld+json">{
"@type": "Event", "url": "%s/concerts/show-1/", "name": "Шоу",
"startDate": "2999-01-01T20:00:00+0300",
"location": {"name": "Клуб", "address": {"streetAddress": "ул. Тест, 1", "addressLocality": ""}},
"offers": {"lowPrice": "10.00", "highPrice": "20.00"}
}</script></html>`

type site struct {
	*httptest.Server
	mu   sync.Mutex
	hits []string
}

func newSite(t *testing.T, robots string, robotsCode int) *site {
	s := &site{}
	s.Server = httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		s.mu.Lock()
		s.hits = append(s.hits, r.URL.Path)
		s.mu.Unlock()

		if r.URL.Path == "/robots.txt" {
			w.WriteHeader(robotsCode)
			io.WriteString(w, robots)
			return
		}
		fmt.Fprintf(w, eventPage, "http://"+r.Host)
	}))
	t.Cleanup(s.Close)
	return s
}

func (s *site) visited(path string) bool {
	s.mu.Lock()
	defer s.mu.Unlock()
	for _, h := range s.hits {
		if h == path {
			return true
		}
	}
	return false
}

func newCollector(s *site, cfg Config, city string) *Collector {
	client := fetch.New("AfishaRBBot/test", time.Millisecond)
	return New("test", "Test", s.URL, city, cfg, client, "AfishaRBBot", slog.New(slog.NewTextHandler(io.Discard, nil)))
}

var siteConfig = Config{
	Pages:      []string{"/", "/private/"},
	Categories: map[string]string{"concerts": "concert"},
}

func TestCollectRespectsRobotsTxt(t *testing.T) {
	s := newSite(t, "User-agent: *\nDisallow: /private/\n", http.StatusOK)

	res, err := newCollector(s, siteConfig, "").Collect(context.Background())
	if err != nil {
		t.Fatal(err)
	}
	events := res.Events
	if len(events) != 1 {
		t.Errorf("событий %d, ожидали 1", len(events))
	}
	if s.visited("/private/") {
		t.Error("страница, закрытая в robots.txt, не должна запрашиваться")
	}
	if st := res.Stats; st.PagesTotal != 2 || st.PagesLoaded != 1 || st.PagesBlocked != 1 || st.PagesFailed != 0 {
		t.Errorf("статистика страниц: %+v", st)
	}
	if !s.visited("/") {
		t.Error("разрешённая страница должна запрашиваться")
	}
}

func TestCollectWithoutRobotsTxtIsAllowed(t *testing.T) {
	s := newSite(t, "", http.StatusNotFound)

	res, err := newCollector(s, siteConfig, "").Collect(context.Background())
	events := res.Events
	if err != nil || len(events) != 1 {
		t.Fatalf("events=%d err=%v", len(events), err)
	}
	if !s.visited("/private/") {
		t.Error("без robots.txt открыты все страницы из настроек")
	}
}

func TestCollectStopsWhenRobotsTxtUnreadable(t *testing.T) {
	s := newSite(t, "", http.StatusForbidden)

	if _, err := newCollector(s, siteConfig, "").Collect(context.Background()); err == nil {
		t.Fatal("если robots.txt недоступен (403), вслепую собирать нельзя")
	}
	if s.visited("/") {
		t.Error("страницы не должны запрашиваться")
	}
}

func TestCollectCityFallback(t *testing.T) {
	s := newSite(t, "", http.StatusNotFound)

	withoutRes, _ := newCollector(s, siteConfig, "минск").Collect(context.Background())
	without := withoutRes.Events
	if len(without) != 1 || len(without[0].CityHints) != 0 {
		t.Fatalf("без city_fallback подсказок города быть не должно: %+v", without)
	}

	cfg := siteConfig
	cfg.CityFallback = true
	withRes, _ := newCollector(s, cfg, "минск").Collect(context.Background())
	with := withRes.Events
	if len(with) != 1 || len(with[0].CityHints) != 1 || !strings.EqualFold(with[0].CityHints[0], "минск") {
		t.Errorf("с city_fallback город источника должен попасть в подсказки: %+v", with)
	}
}
