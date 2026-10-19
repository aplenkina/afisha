package jsonld

import (
	"os"
	"testing"
	"time"
)

var testConfig = Config{Categories: map[string]string{
	"bilety-na-koncert": "concert",
	"bilety-v-teatr":    "theatre",
	"raznoe":            "workshop",
}}

func TestParse(t *testing.T) {
	page, err := os.ReadFile("testdata/list.html")
	if err != nil {
		t.Fatal(err)
	}

	// Дата до всех событий в фикстуре, чтобы фильтр «прошедшие» не мешал.
	events, stats := Parse(page, time.Date(2026, 10, 1, 0, 0, 0, 0, time.UTC), testConfig)

	if stats.Broken != 0 {
		t.Fatalf("не разобралось блоков: %d (сырые переводы строк и кривые описания должны переживаться)", stats.Broken)
	}
	if len(events)+stats.NoCategory != 6 {
		t.Fatalf("ожидали 6 событий, получили %d + %d без категории", len(events), stats.NoCategory)
	}
	if len(events) == 0 {
		t.Fatal("ни одного события с категорией")
	}

	for _, e := range events {
		if e.ExternalID == "" || e.Title == "" || e.StartsAt.IsZero() || e.CategoryAlias == "" {
			t.Errorf("неполное событие: %+v", e)
		}
		// Туры «В различных местах» без города: раннер их пропустит, привязать их не к чему.
		if len(e.CityHints) == 0 && e.VenueName != "В различных местах" {
			t.Errorf("нет подсказок города: %+v", e)
		}
	}
}

func TestParseSkipsPast(t *testing.T) {
	page, err := os.ReadFile("testdata/list.html")
	if err != nil {
		t.Fatal(err)
	}

	events, stats := Parse(page, time.Date(2030, 1, 1, 0, 0, 0, 0, time.UTC), testConfig)
	if len(events) != 0 || stats.Past == 0 {
		t.Fatalf("все события должны быть прошедшими: events=%d past=%d", len(events), stats.Past)
	}
}

func TestToEventFirstFixtureDetails(t *testing.T) {
	page, _ := os.ReadFile("testdata/list.html")
	events, _ := Parse(page, time.Date(2026, 10, 1, 0, 0, 0, 0, time.UTC), testConfig)

	var found bool
	for _, e := range events {
		if e.ExternalID == "bilety-na-koncert/festival-elektronnoj-muzyki-kosmos-nash-unique-17-oktyabrya-2026" {
			found = true
			if e.CategoryAlias != "concert" || e.VenueName != "Арт-пространство ЭХО" {
				t.Errorf("неверно разобрано: %+v", e)
			}
			if e.PriceMin == nil || *e.PriceMin != "100.00" || e.PriceMax == nil || *e.PriceMax != "220.00" {
				t.Errorf("цены: %+v %+v", e.PriceMin, e.PriceMax)
			}
			if e.EndsAt != nil {
				t.Errorf("endDate без времени не должна становиться ends_at")
			}
		}
	}
	if !found {
		t.Fatal("фестиваль не найден")
	}
}
