package runner

import (
	"fmt"
	"sort"
	"strings"
	"time"
	"unicode/utf8"

	"afisha/internal/collector"
)

// Лимит Telegram 4096 символов, оставляем запас.
const telegramLimit = 3900

type NewEvent struct {
	Title    string
	StartsAt time.Time
	Place    string
}

type SourceReport struct {
	Slug, Name string
	Status     string
	Err        string
	Took       time.Duration
	Stats      collector.Stats

	Collected, Created, Updated, NoCity, NoCategory, Failed int

	NewByCategory map[string]int
	New           []NewEvent
}

func (r SourceReport) Healthy() bool {
	return r.Status == StatusOK && r.Failed == 0 && r.Stats.PagesFailed == 0 && r.Stats.PagesBlocked == 0
}

type Summary struct {
	Reports       []SourceReport
	Took          time.Duration
	At            time.Time
	Loc           *time.Location
	Disabled      int
	NoCollector   int // включены, но настроек сборщика нет
	CategoryNames map[string]string
}

func (s Summary) healthy() bool {
	for _, r := range s.Reports {
		if !r.Healthy() {
			return false
		}
	}
	return true
}

func (s Summary) Format() string {
	for _, maxNew := range []int{10, 5, 0} {
		text := s.format(maxNew)
		if utf8.RuneCountInString(text) <= telegramLimit {
			return text
		}
	}

	r := []rune(s.format(0))
	return string(r[:telegramLimit-1]) + "…"
}

func (s Summary) format(maxNew int) string {
	loc := s.Loc
	if loc == nil {
		loc = time.UTC
	}

	var b strings.Builder
	if s.healthy() {
		b.WriteString("✅ Сбор событий завершён\n")
	} else {
		b.WriteString("⚠️ Сбор событий завершён с замечаниями\n")
	}
	fmt.Fprintf(&b, "%s · %s\n", s.At.In(loc).Format("02.01.2006 15:04"), duration(s.Took))

	for _, r := range s.Reports {
		b.WriteString("\n")
		s.writeSource(&b, r, loc, maxNew)
	}

	if s.Disabled > 0 || s.NoCollector > 0 {
		fmt.Fprintf(&b, "\nНе запускались: выключено в админке — %d, без настроек сборщика — %d\n", s.Disabled, s.NoCollector)
	}

	return strings.TrimRight(b.String(), "\n")
}

func (s Summary) writeSource(b *strings.Builder, r SourceReport, loc *time.Location, maxNew int) {
	fmt.Fprintf(b, "▸ %s — %s (%s)\n", r.Name, statusLine(r), duration(r.Took))

	if r.Status == StatusError && r.Collected == 0 {
		return
	}
	if r.Status == StatusEmpty {
		writePages(b, r.Stats)
		return
	}

	fmt.Fprintf(b, "  Собрано: %d\n", r.Collected)
	fmt.Fprintf(b, "  Новых: %d · обновлено: %d\n", r.Created, r.Updated)

	if skipped := r.NoCity + r.NoCategory; skipped > 0 {
		fmt.Fprintf(b, "  Пропущено при записи: %d (город не определён: %d, категории нет в базе: %d)\n",
			skipped, r.NoCity, r.NoCategory)
	}
	if r.Failed > 0 {
		fmt.Fprintf(b, "  Ошибок записи: %d\n", r.Failed)
	}

	writePages(b, r.Stats)

	if dropped := r.Stats.Past + r.Stats.NoCategory + r.Stats.Broken; dropped > 0 {
		fmt.Fprintf(b, "  Отброшено на сайте: прошедших — %d, из разделов без категории — %d, нечитаемых блоков — %d\n",
			r.Stats.Past, r.Stats.NoCategory, r.Stats.Broken)
	}

	if len(r.NewByCategory) > 0 {
		fmt.Fprintf(b, "  Новые по категориям: %s\n", s.categories(r.NewByCategory))
	}

	if maxNew > 0 && len(r.New) > 0 {
		b.WriteString("  Новые события:\n")
		shown := r.New
		if len(shown) > maxNew {
			shown = shown[:maxNew]
		}
		for _, e := range shown {
			fmt.Fprintf(b, "  • %s — %s\n", truncate(e.Title, 60), eventWhen(e, loc))
		}
		if rest := len(r.New) - len(shown); rest > 0 {
			fmt.Fprintf(b, "  … и ещё %d\n", rest)
		}
	}
}

func statusLine(r SourceReport) string {
	switch {
	case r.Status == StatusEmpty:
		return "⚠️ источник вернул 0 событий (вероятно, изменилась вёрстка или закрыт доступ)"
	case r.Status == StatusError:
		return "❌ " + truncate(r.Err, 200)
	case !r.Healthy():
		return "⚠️ с замечаниями"
	default:
		return "✅ успешно"
	}
}

func writePages(b *strings.Builder, st collector.Stats) {
	if st.PagesTotal == 0 {
		return
	}
	fmt.Fprintf(b, "  Страницы: загружено %d из %d", st.PagesLoaded, st.PagesTotal)
	if st.PagesBlocked > 0 || st.PagesFailed > 0 {
		fmt.Fprintf(b, " (закрыто в robots.txt: %d, не загрузилось: %d)", st.PagesBlocked, st.PagesFailed)
	}
	b.WriteString("\n")
}

func (s Summary) categories(byAlias map[string]int) string {
	type item struct {
		name string
		n    int
	}
	items := make([]item, 0, len(byAlias))
	for alias, n := range byAlias {
		name := s.CategoryNames[alias]
		if name == "" {
			name = alias
		}
		items = append(items, item{name, n})
	}
	sort.Slice(items, func(i, j int) bool {
		if items[i].n != items[j].n {
			return items[i].n > items[j].n
		}
		return items[i].name < items[j].name
	})

	parts := make([]string, len(items))
	for i, it := range items {
		parts[i] = fmt.Sprintf("%s — %d", it.name, it.n)
	}
	return strings.Join(parts, ", ")
}

func eventWhen(e NewEvent, loc *time.Location) string {
	when := e.StartsAt.In(loc).Format("02.01 15:04")
	if e.Place != "" {
		return when + ", " + e.Place
	}
	return when
}

func duration(d time.Duration) string {
	if d < time.Minute {
		return fmt.Sprintf("%d с", int(d.Round(time.Second).Seconds()))
	}
	return fmt.Sprintf("%d мин %d с", int(d.Minutes()), int(d.Seconds())%60)
}

func truncate(s string, max int) string {
	s = strings.Join(strings.Fields(s), " ")
	if r := []rune(s); len(r) > max {
		return string(r[:max-1]) + "…"
	}
	return s
}

func upperFirst(s string) string {
	r := []rune(s)
	if len(r) == 0 {
		return s
	}
	r[0] = []rune(strings.ToUpper(string(r[0])))[0]
	return string(r)
}
