package store

import (
	"context"
	"os"
	"testing"
	"time"

	"afisha/internal/collector"

	"github.com/jackc/pgx/v5/pgxpool"
)

func TestImportEventTitles(t *testing.T) {
	databaseURL := os.Getenv("AFISHA_IMPORT_TEST_DATABASE_URL")
	if databaseURL == "" {
		t.Skip("AFISHA_IMPORT_TEST_DATABASE_URL is required for the PostgreSQL integration test")
	}
	ctx := context.Background()
	pool, err := pgxpool.New(ctx, databaseURL)
	if err != nil {
		t.Fatal(err)
	}
	t.Cleanup(pool.Close)

	var fixtureSource, bezkassira, cityID, categoryID int
	if err := pool.QueryRow(ctx, `SELECT source.id, source.city_id, category.id
		FROM source CROSS JOIN category WHERE source.slug = 'bezkassira' AND category.alias = 'party'`).
		Scan(&bezkassira, &cityID, &categoryID); err != nil {
		t.Fatal(err)
	}
	if err := pool.QueryRow(ctx, `INSERT INTO source (city_id, name, slug, source_url, available, created_at)
		VALUES ($1, 'Event title test', 'integration-event-title', 'https://example.com', false, now()) RETURNING id`, cityID).
		Scan(&fixtureSource); err != nil {
		t.Fatal(err)
	}
	t.Cleanup(func() {
		if _, err := pool.Exec(ctx, `DELETE FROM source WHERE id = $1`, fixtureSource); err != nil {
			t.Error(err)
		}
	})

	st := New(pool)
	now := time.Now().UTC().Truncate(time.Second)
	startsAt := now.Add(24 * time.Hour)
	const prefix = "integration-event-title-"
	t.Cleanup(func() {
		if _, err := pool.Exec(ctx, `DELETE FROM event WHERE slug LIKE $1`, prefix+"%"); err != nil {
			t.Error(err)
		}
	})

	save := func(source int, id, title string, start time.Time, observed, wantCreated bool) {
		t.Helper()
		e := collector.Event{Title: title, Slug: prefix + id, ExternalID: prefix + id,
			StartsAt: start, VenueName: "Title Test " + id, Address: "Test address",
			SourceURL: "https://example.com/" + prefix + id}
		if observed {
			e.Observation = &collector.Observation{Title: title, URL: e.SourceURL, Status: collector.ObservedScheduled,
				CheckedAt: now, StartsAt: start.Format(time.RFC3339), VerifiedForPublication: true}
		}
		if created, err := st.Save(ctx, source, cityID, categoryID, e); err != nil || created != wantCreated {
			t.Fatalf("Save(%q) = %v, %v, want created=%v", id, created, err, wantCreated)
		}
	}

	save(fixtureSource, "original", "Комедия «ПРОВЕРКА:  РАЗ, ДВА»", startsAt, false, true)
	var originalID int
	if err := pool.QueryRow(ctx, `UPDATE event SET status = 'published' WHERE slug = $1 RETURNING id`, prefix+"original").
		Scan(&originalID); err != nil {
		t.Fatal(err)
	}
	save(bezkassira, "same", "проверка—раз.два", startsAt, true, true)
	save(bezkassira, "same", "Проверка: раз, два", startsAt, true, false)
	save(fixtureSource, "same-unobserved", "ПРОВЕРКА, РАЗ ДВА", startsAt, false, true)
	save(fixtureSource, "other-time", "ПРОВЕРКА, РАЗ ДВА", startsAt.Add(time.Hour), false, true)
	save(fixtureSource, "review", "КОНЦЕРТ — ДЛЯ СКРИПКИ", startsAt, false, true)

	var display, status string
	var duplicateOf *int
	var reviewReason *string
	load := func(id string) {
		t.Helper()
		if err := pool.QueryRow(ctx, `SELECT title, status, duplicate_of_id, title_review_reason FROM event WHERE slug = $1`, prefix+id).
			Scan(&display, &status, &duplicateOf, &reviewReason); err != nil {
			t.Fatal(err)
		}
	}
	load("original")
	if display != "Проверка:  раз, два" || status != "published" || reviewReason != nil {
		t.Fatalf("unexpected original: title=%q status=%q review=%v", display, status, reviewReason)
	}
	for _, id := range []string{"same", "same-unobserved"} {
		load(id)
		if status != "duplicate" || duplicateOf == nil || *duplicateOf != originalID {
			t.Fatalf("%s was not attached to %d: status=%q original=%v", id, originalID, status, duplicateOf)
		}
	}
	load("other-time")
	if status != "draft" || duplicateOf != nil {
		t.Fatalf("card with another session was matched: status=%q original=%v", status, duplicateOf)
	}
	load("review")
	if status != "draft" || reviewReason == nil {
		t.Fatalf("ambiguous title was not held for review: status=%q review=%v", status, reviewReason)
	}
}
