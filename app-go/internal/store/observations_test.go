package store

import (
	"context"
	"os"
	"testing"
	"time"

	"afisha/internal/collector"

	"github.com/jackc/pgx/v5/pgxpool"
)

func TestBezKassiraSessionLifecycle(t *testing.T) {
	databaseURL := os.Getenv("AFISHA_IMPORT_TEST_DATABASE_URL")
	if databaseURL == "" {
		t.Skip("AFISHA_IMPORT_TEST_DATABASE_URL is required for the PostgreSQL integration test")
	}
	ctx := context.Background()
	pool, err := pgxpool.New(ctx, databaseURL)
	if err != nil {
		t.Fatal(err)
	}
	defer pool.Close()
	var sourceID, cityID, categoryID int
	err = pool.QueryRow(ctx, `SELECT source.id, source.city_id, category.id
		FROM source CROSS JOIN category WHERE source.slug = 'bezkassira' AND category.alias = 'party'`).
		Scan(&sourceID, &cityID, &categoryID)
	if err != nil {
		t.Fatal(err)
	}

	st := New(pool)
	now := time.Now().UTC().Truncate(time.Second)
	start := now.Add(24 * time.Hour)
	const externalID = "integration-session"
	const sourceURL = "https://bezkassira.by/integration-0999/"
	remove := func() {
		if _, err := pool.Exec(ctx, "DELETE FROM event WHERE source_id = $1 AND external_id = $2", sourceID, externalID); err != nil {
			t.Error(err)
		}
	}
	defer remove()
	e := collector.Event{
		ExternalID: externalID, Title: "Integration Session", SourceURL: sourceURL,
		TicketURL: sourceURL + "buy/", VenueName: "Integration Venue", Address: "Integration Address", StartsAt: start,
		Observation: &collector.Observation{
			URL: sourceURL, Status: collector.ObservedScheduled, CheckedAt: now, StartsAt: start.Format(time.RFC3339),
		},
	}
	save := func(e collector.Event, wantCreated bool) {
		t.Helper()
		created, err := st.Save(ctx, sourceID, cityID, categoryID, e)
		if err != nil || created != wantCreated {
			t.Fatalf("Save returned created=%v err=%v; want created=%v", created, err, wantCreated)
		}
	}
	assertState := func(wantStatus, wantObservation string, wantScheduled, wantCancelled int) {
		t.Helper()
		var status, observation string
		var scheduled, cancelled int
		err := pool.QueryRow(ctx, `
			SELECT e.status, e.import_observation->>'status',
				count(*) FILTER (WHERE se.status = 'scheduled'), count(*) FILTER (WHERE se.status = 'cancelled')
			FROM event e LEFT JOIN event_session se ON se.event_id = e.id
			WHERE e.source_id = $1 AND e.external_id = $2 GROUP BY e.id`, sourceID, externalID).
			Scan(&status, &observation, &scheduled, &cancelled)
		if err != nil || status != wantStatus || observation != wantObservation ||
			scheduled != wantScheduled || cancelled != wantCancelled {
			t.Fatalf("state=%q observation=%q scheduled=%d cancelled=%d err=%v", status, observation, scheduled, cancelled, err)
		}
	}
	save(e, true)
	save(e, false)
	assertState("draft", "scheduled", 1, 0)
	if _, err := pool.Exec(ctx, "UPDATE event SET status = 'published' WHERE source_id = $1 AND external_id = $2", sourceID, externalID); err != nil {
		t.Fatal(err)
	}
	save(collector.Event{ExternalID: externalID, Observation: &collector.Observation{
		Status: collector.ObservedUnavailable, CheckError: "timeout", CheckedAt: now.Add(time.Minute), URL: sourceURL,
	}}, false)
	assertState("published", "scheduled", 1, 0)
	var checkError string
	err = pool.QueryRow(ctx, "SELECT import_observation->>'checkError' FROM event WHERE source_id = $1 AND external_id = $2",
		sourceID, externalID).Scan(&checkError)
	if err != nil || checkError != "timeout" {
		t.Fatalf("checkError=%q err=%v", checkError, err)
	}

	stale := *e.Observation
	e.StartsAt = start.Add(2 * time.Hour)
	e.Observation.StartsAt = e.StartsAt.Format(time.RFC3339)
	e.Observation.CheckedAt = now.Add(2 * time.Minute)
	save(e, false)
	assertState("draft", "scheduled", 1, 1)

	save(collector.Event{ExternalID: externalID, Observation: &collector.Observation{
		Status: collector.ObservedPostponed, CheckedAt: now.Add(3 * time.Minute), URL: sourceURL,
	}}, false)
	assertState("draft", "postponed", 0, 2)
	e.Observation = &stale
	e.StartsAt = start
	save(e, false)
	assertState("draft", "postponed", 0, 2)
	e.Observation.CheckedAt = now.Add(4 * time.Minute)
	save(e, false)
	assertState("draft", "scheduled", 1, 1)
	save(collector.Event{ExternalID: externalID, Observation: &collector.Observation{
		Status: collector.ObservedCancelled, CheckedAt: now.Add(5 * time.Minute), URL: sourceURL,
	}}, false)
	assertState("draft", "cancelled", 0, 2)
}

func TestBezKassiraPublication(t *testing.T) {
	databaseURL := os.Getenv("AFISHA_IMPORT_TEST_DATABASE_URL")
	if databaseURL == "" {
		t.Skip("AFISHA_IMPORT_TEST_DATABASE_URL is required for the PostgreSQL integration test")
	}
	ctx := context.Background()
	pool, err := pgxpool.New(ctx, databaseURL)
	if err != nil {
		t.Fatal(err)
	}
	defer pool.Close()
	var sourceID, cityID, categoryID int
	var originalConfig []byte
	err = pool.QueryRow(ctx, `SELECT source.id, source.city_id, category.id, source.config
		FROM source CROSS JOIN category WHERE source.slug = 'bezkassira' AND category.alias = 'party'`).
		Scan(&sourceID, &cityID, &categoryID, &originalConfig)
	if err != nil {
		t.Fatal(err)
	}
	defer func() {
		if _, err := pool.Exec(ctx, `DELETE FROM event WHERE slug LIKE 'bezkassira-pubtest-%' OR slug = 'pubtest-editor'`); err != nil {
			t.Error(err)
		}
		if _, err := pool.Exec(ctx, `UPDATE source SET config = $1::jsonb WHERE id = $2`, originalConfig, sourceID); err != nil {
			t.Error(err)
		}
	}()

	st := New(pool)
	now := time.Now().UTC().Truncate(time.Second)
	startsAt := now.Add(24 * time.Hour)
	add := func(id, title, venue string, starts time.Time, verified bool) {
		t.Helper()
		u := "https://bezkassira.by/pubtest-" + id + "/"
		e := collector.Event{
			ExternalID: "pubtest-" + id, Title: title, SourceURL: u, TicketURL: u + "buy/",
			VenueName: venue, Address: "Publication Test Address", StartsAt: starts,
			Observation: &collector.Observation{
				CheckedAt: now, URL: u, Title: title, Venue: venue, Status: collector.ObservedScheduled,
				StartsAt: starts.Format(time.RFC3339), VerifiedForPublication: verified,
			},
		}
		if _, err := st.Save(ctx, sourceID, cityID, categoryID, e); err != nil {
			t.Fatal(err)
		}
	}
	status := func(id, wanted string) {
		t.Helper()
		var got string
		err := pool.QueryRow(ctx, `SELECT status FROM event WHERE slug = $1`, "bezkassira-pubtest-"+id).Scan(&got)
		if err != nil || got != wanted {
			t.Fatalf("%s got=%q want=%q: %v", id, got, wanted, err)
		}
	}

	const venue = "Publication Test Venue"
	const otherVenue = "Other Publication Test Venue"
	add("1", "Publication test", venue, startsAt, true)
	add("2", "  publication   test", otherVenue, startsAt, true)
	add("3", "Publication test", venue, startsAt.Add(time.Hour), true)
	add("4", "Needs a review", venue, startsAt, false)
	add("5", "Conflicting time", venue, startsAt, true)
	var otherID int
	err = pool.QueryRow(ctx, `INSERT INTO event (title, slug, category_id, city_id, venue_id, is_free,
		ticket_url, status, created_at, updated_at)
		SELECT 'Conflicting time', 'pubtest-editor', category_id, city_id, venue_id, false,
			source_url || 'buy/?ref=operator', 'published', now(), now()
		FROM event WHERE slug = 'bezkassira-pubtest-5' RETURNING id`).Scan(&otherID)
	if err != nil {
		t.Fatal(err)
	}
	if _, err := pool.Exec(ctx, `INSERT INTO event_session (event_id, starts_at, status)
		VALUES ($1, $2, 'scheduled')`, otherID, startsAt.Add(2*time.Hour)); err != nil {
		t.Fatal(err)
	}
	status("1", "draft")
	want := BezKassiraPublication{Published: 2, Duplicate: 1, Review: 1, Conflict: 1}
	preview, err := st.PublishBezKassiraDrafts(ctx, false)
	if err != nil || preview != want {
		t.Fatalf("preview=%+v want=%+v: %v", preview, want, err)
	}
	status("1", "draft")
	result, err := st.PublishBezKassiraDrafts(ctx, true)
	if err != nil || result != preview {
		t.Fatalf("result=%+v preview=%+v: %v", result, preview, err)
	}
	status("1", "published")
	status("2", "duplicate")
	status("3", "published")
	status("4", "draft")
	status("5", "draft")
	add("6", "New confirmed publication", venue, startsAt, true)
	add("7", "Publication test", otherVenue, startsAt, true)
	status("6", "published")
	status("7", "duplicate")
	add("7", "Publication test", venue, startsAt, true)
	status("7", "duplicate")
	add("7", "Publication test", venue, startsAt.Add(2*time.Hour), true)
	status("7", "draft")
	// Moving a card back to drafts is a moderation decision, not an invitation to republish.
	if _, err := pool.Exec(ctx, "UPDATE event SET status = 'draft' WHERE slug = 'bezkassira-pubtest-6'"); err != nil {
		t.Fatal(err)
	}
	add("6", "New confirmed publication", venue, startsAt, true)
	status("6", "draft")
}
