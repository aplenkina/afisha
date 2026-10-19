package runner

import (
	"fmt"
	"strings"
	"testing"
	"time"
	"unicode/utf8"

	"afisha/internal/collector"
)

var minskZone = time.FixedZone("Minsk", 3*3600)

func summaryWith(reports ...SourceReport) Summary {
	return Summary{
		Reports: reports, Took: 75 * time.Second,
		At:            time.Date(2026, 10, 18, 14, 22, 0, 0, time.UTC),
		Loc:           minskZone,
		CategoryNames: map[string]string{"concert": "Концерты", "sport": "Спорт"},
	}
}

func okReport(newEvents int) SourceReport {
	r := SourceReport{
		Slug: "s", Name: "Источник", Status: StatusOK, Took: 11 * time.Second,
		Collected: newEvents, Created: newEvents,
		NewByCategory: map[string]int{"concert": newEvents},
	}
	for i := 0; i < newEvents; i++ {
		r.New = append(r.New, NewEvent{
			Title:    fmt.Sprintf("Событие номер %d с довольно длинным названием, которое придётся обрезать, чтобы список был читаемым", i),
			StartsAt: time.Date(2026, 10, 21, 16, 0, 0, 0, time.UTC),
			Place:    "Минск, Клуб",
		})
	}
	return r
}

func TestFormatHeaderTimeAndDuration(t *testing.T) {
	text := summaryWith(okReport(1)).Format()

	for _, want := range []string{"✅ Сбор событий завершён", "18.10.2026 17:22 · 1 мин 15 с", "(11 с)"} {
		if !strings.Contains(text, want) {
			t.Errorf("нет %q:\n%s", want, text)
		}
	}
}

func TestFormatCategoriesSortedByCountThenName(t *testing.T) {
	r := okReport(0)
	r.NewByCategory = map[string]int{"sport": 2, "concert": 5, "unknown_alias": 2}

	text := summaryWith(r).Format()

	// При равном числе — по названию (побайтово: латиница раньше кириллицы), неизвестный alias выводится как есть.
	if !strings.Contains(text, "Концерты — 5, unknown_alias — 2, Спорт — 2") {
		t.Errorf("порядок или названия категорий неверны:\n%s", text)
	}
}

func TestFormatListsAtMost10NewEventsAndCountsRest(t *testing.T) {
	text := summaryWith(okReport(14)).Format()

	if got := strings.Count(text, "  • "); got != 10 {
		t.Errorf("в списке %d событий, ожидали 10", got)
	}
	if !strings.Contains(text, "… и ещё 4") {
		t.Errorf("нет счётчика остатка:\n%s", text)
	}
	if !strings.Contains(text, "…") || strings.Contains(text, "читаемым") {
		t.Errorf("длинные названия должны обрезаться:\n%s", text)
	}
}

func TestFormatFitsTelegramLimitByShrinkingLists(t *testing.T) {
	var reports []SourceReport
	for i := 0; i < 12; i++ {
		r := okReport(40)
		r.Name = fmt.Sprintf("Источник %d", i)
		reports = append(reports, r)
	}

	text := summaryWith(reports...).Format()

	if n := utf8.RuneCountInString(text); n > telegramLimit {
		t.Fatalf("сообщение %d символов, лимит %d", n, telegramLimit)
	}
	if !strings.Contains(text, "Источник 0") {
		t.Error("заголовки источников должны остаться")
	}
}

func TestFormatHardTruncationAsLastResort(t *testing.T) {
	var reports []SourceReport
	for i := 0; i < 200; i++ {
		r := okReport(0)
		r.Name = fmt.Sprintf("Источник %d", i)
		reports = append(reports, r)
	}

	text := summaryWith(reports...).Format()

	if n := utf8.RuneCountInString(text); n > telegramLimit {
		t.Fatalf("сообщение %d символов, лимит %d", n, telegramLimit)
	}
	if !strings.HasSuffix(text, "…") {
		t.Error("обрезанный текст должен заканчиваться многоточием")
	}
}

func TestHealthy(t *testing.T) {
	cases := map[string]struct {
		r    SourceReport
		want bool
	}{
		"чисто":           {SourceReport{Status: StatusOK}, true},
		"ошибки записи":   {SourceReport{Status: StatusOK, Failed: 1}, false},
		"страница упала":  {SourceReport{Status: StatusOK, Stats: collector.Stats{PagesFailed: 1}}, false},
		"закрыто robots":  {SourceReport{Status: StatusOK, Stats: collector.Stats{PagesBlocked: 1}}, false},
		"пустой источник": {SourceReport{Status: StatusEmpty}, false},
		"ошибка":          {SourceReport{Status: StatusError}, false},
	}
	for name, c := range cases {
		if got := c.r.Healthy(); got != c.want {
			t.Errorf("%s: Healthy = %v, ожидали %v", name, got, c.want)
		}
	}
}

func TestDurationFormat(t *testing.T) {
	cases := map[time.Duration]string{
		400 * time.Millisecond: "0 с",
		11 * time.Second:       "11 с",
		59 * time.Second:       "59 с",
		75 * time.Second:       "1 мин 15 с",
		10 * time.Minute:       "10 мин 0 с",
	}
	for d, want := range cases {
		if got := duration(d); got != want {
			t.Errorf("duration(%v) = %q, ожидали %q", d, got, want)
		}
	}
}
