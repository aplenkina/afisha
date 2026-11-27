package citydog

import (
	"fmt"
	"testing"
	"time"
)

func TestParseArticle(t *testing.T) {
	const block = `<h2>Смотрим кино</h2>
		<blockquote data-instgrm-permalink="https://www.instagram.com/p/Film/?utm_source=ig_embed"></blockquote>
		<ul><li>%s</li><li>Бар «Персонажи» (ул. Карла Маркса, 33).</li><li>Вход: 10–20 руб.</li></ul>
		<p>Смотрим фильм «Отец».</p>`
	published := `<script type="application/ld+json">{"@type":"NewsArticle","datePublished":"2026-12-30T19:00:00+03:00"}</script>`
	address := "https://citydog.io/post/weekend/"
	now, _ := time.Parse(time.RFC3339, "2027-01-02T13:00:00+03:00")
	page := func(date string) []byte {
		return []byte(published + `<article class="postPage-contentText"><div>` + fmt.Sprintf(block, date) + `</div></article>`)
	}
	events, past, broken := parseArticle(page("2 студзеня, 17:00."), address, "Минск", now)
	if len(events) != 1 || past+broken != 0 {
		t.Fatalf("events=%v past=%d broken=%d", events, past, broken)
	}
	e := events[0]
	if e.Title != "Кинопоказ «Отец»" || e.CategoryAlias != "cinema" || e.StartsAt.Format(time.RFC3339) != "2027-01-02T17:00:00+03:00" ||
		e.SourceURL != address || e.Observation.OriginalURL != "https://www.instagram.com/p/Film/" ||
		e.Observation.VerifiedForPublication || e.PriceMin == nil || *e.PriceMin != "10" || e.PriceMax == nil || *e.PriceMax != "20" {
		t.Fatalf("unexpected event: %+v", e)
	}
	changed, _, _ := parseArticle(page("2 января, 19:00."), address, "Минск", now)
	if len(changed) != 1 || changed[0].ExternalID != e.ExternalID {
		t.Fatalf("correcting the time changed the event identity: %+v", changed)
	}
	if got, past, broken := parseArticle(page("2 января, 12:00."), address, "Минск", now); len(got) != 0 || past != 1 || broken != 0 {
		t.Fatalf("past session: events=%v past=%d broken=%d", got, past, broken)
	}
	if got, past, broken := parseArticle(page("32 января, 17:00."), address, "Минск", now); len(got) != 0 || past != 0 || broken != 1 {
		t.Fatalf("invalid date: events=%v past=%d broken=%d", got, past, broken)
	}
}

func TestRoundupPaths(t *testing.T) {
	const page = `<a href="/post/current/">Куды пайсці ў выхадныя ў Мінску</a>
		<a href="/post/current/">Куды пайсці ў выхадныя ў Мінску</a>
		<a href="/post/local/">Куда пойти в выходные в Минске</a>
		<a href="/post/trip/">Куда поехать в выходные</a>
		<a href="https://example.com/post/elsewhere/">Куда пойти в выходные в Минске</a>`
	paths := roundupPaths([]byte(page))
	if len(paths) != 2 || paths[0] != "/post/current/" || paths[1] != "/post/local/" {
		t.Fatalf("paths=%v", paths)
	}
}
