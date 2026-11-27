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

	"afisha/internal/collector"
	"afisha/internal/fetch"
)

func TestBezKassiraDiscoveryAndRecheck(t *testing.T) {
	var base string
	var requests []string
	var requestMu sync.Mutex
	card := func(path, title, start, end, status string) string {
		return fmt.Sprintf(`<script type="application/ld+json">{
			"@type":"Event","url":"%s%s","name":"%s","description":"23:00 - 05:00",
			"startDate":"%s","endDate":"%s","eventStatus":"%s",
			"location":{"name":"Пространство «ЭХО»","address":{"addressLocality":"Минск","streetAddress":"Независимости, 95/5"}},
			"image":{"@type":"ImageObject","contentUrl":"%s/image.webp"},
			"offers":{"url":"%s%sbuy/","priceCurrency":"BYN","price":20.00,"availability":"https://schema.org/InStock"}
		}</script>`, base, path, title, start, end, status, base, base, path)
	}
	handler := func(w http.ResponseWriter, r *http.Request) {
		requestMu.Lock()
		requests = append(requests, r.URL.RequestURI())
		requestMu.Unlock()
		switch r.URL.Path {
		case "/robots.txt":
			fmt.Fprint(w, "User-agent: *\nDisallow: /forbidden-99/\nDisallow: /*?\n")
		case "/events/party-minsk/":
			fmt.Fprintf(w, `<a href='/night-10/?utm_source=abc&amp;another=yes'>Event</a><a href="%s/night-10/">repeat</a><a href="https://other.invalid/wrong-8/">external</a><a href="/forbidden-99/">blocked</a>`, base)
		case "/sitemap.xml":
			fmt.Fprintf(w, `<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"><url><loc>%s/night-11/</loc></url></urlset>`, base)
		case "/night-10/", "/night-11/":
			fmt.Fprint(w, `<div class="breadcrumbs"><a href="/events/party-minsk/">Party</a></div><div class="price">Цена билетов: 25 ƃ</div>`)
			fmt.Fprint(w, card("/unrelated-30/", "Recommended", "2099-11-27T23:00:00+03:00", "2099-11-27T23:00:00+03:00", "EventScheduled"))
			fmt.Fprint(w, card(r.URL.Path, "Edenn", "2099-11-27T23:00:00+03:00", "2099-11-27T23:00:00+03:00", "EventScheduled"))
		case "/cancelled-12/":
			fmt.Fprint(w, `<div class="notify-transfer-cancel">Мероприятие отменено</div>`)
		default:
			http.NotFound(w, r)
		}
	}
	server := httptest.NewServer(http.HandlerFunc(handler))
	defer server.Close()
	base = server.URL
	known := func(_ context.Context, recent bool) ([]string, error) {
		if recent {
			return []string{base + "/cancelled-12/"}, nil
		}
		return nil, nil
	}
	c := NewBezKassira("BezKassira", base, "Минск", Config{
		Pages: []string{"/events/party-minsk/"}, Categories: map[string]string{"events/party-minsk": "party"},
	}, fetch.New("test", 0), "test", slog.New(slog.NewTextHandler(io.Discard, nil)), known)
	result, err := c.Collect(context.Background())
	if err != nil || len(result.Events) != 2 {
		t.Fatalf("Collect returned %+v, %v", result, err)
	}
	event := result.Events[0]
	if event.ExternalID != "10" || event.Title != "Edenn" || event.EndsAt != nil || event.ImageURL != base+"/image.webp" || event.VenueName != "ЭХО" ||
		event.PriceMin == nil || *event.PriceMin != "25.00" || len(event.Observation.Issues) != 2 || event.Observation.PriceMin == nil || *event.Observation.PriceMin != "20.00" {
		t.Fatalf("wrong primary event: %+v, observation %+v", event, event.Observation)
	}
	requestMu.Lock()
	all := strings.Join(requests, ",")
	requestMu.Unlock()
	if strings.Count(all, "/night-10/") != 1 || strings.ContainsAny(all, "?") || strings.Contains(all, "forbidden-99") {
		t.Fatalf("unexpected requests: %s", all)
	}

	requestMu.Lock()
	requests = nil
	requestMu.Unlock()
	result, err = c.CollectRecent(context.Background())
	if err != nil || len(result.Events) != 1 || result.Events[0].Observation.Status != collector.ObservedCancelled {
		t.Fatalf("recent cancellation returned %+v, %v", result, err)
	}
	requestMu.Lock()
	got := strings.Join(requests, ",")
	requestMu.Unlock()
	if got != "/robots.txt,/cancelled-12/" {
		t.Fatalf("targeted refresh requested %s", got)
	}
}
