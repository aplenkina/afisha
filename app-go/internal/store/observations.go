package store

import (
	"context"
	"encoding/json"
	"errors"
	"time"

	"afisha/internal/collector"
	"afisha/internal/collector/eventtitle"

	"github.com/jackc/pgx/v5"
)

// KnownEventURLs keeps published and draft cards under observation even after they leave the listing.
func (s *Store) KnownEventURLs(ctx context.Context, sourceID int, recent bool) ([]string, error) {
	rows, err := s.pool.Query(ctx, `
		SELECT COALESCE(e.import_observation->>'url', e.source_url)
		FROM event e
		WHERE e.source_id = $1 AND e.source_url IS NOT NULL
		  AND e.status IN ('draft', 'published', 'duplicate')
		  AND (EXISTS (
			SELECT 1 FROM event_session se WHERE se.event_id = e.id
			  AND COALESCE(se.ends_at, se.starts_at) >= now() - interval '1 day'
			  AND (NOT $2 OR se.starts_at <= now() + interval '48 hours')
		  ) OR (NOT $2 AND e.import_observation->>'status' IN ('postponed', 'review')
			AND e.created_at >= now() - interval '1 year'))
		ORDER BY e.id`, sourceID, recent)
	if err != nil {
		return nil, err
	}
	return pgx.CollectRows(rows, pgx.RowTo[string])
}

func (s *Store) saveObservation(ctx context.Context, tx pgx.Tx, sourceID, cityID, categoryID int, source string, e collector.Event, title eventtitle.Title) (bool, error) {
	observation := *e.Observation
	var eventID int
	var eventStatus string
	var previousJSON []byte
	err := tx.QueryRow(ctx, `
		SELECT id, status, import_observation FROM event WHERE source_id = $1 AND external_id = $2 FOR UPDATE`,
		sourceID, e.ExternalID).Scan(&eventID, &eventStatus, &previousJSON)
	if err != nil && !errors.Is(err, pgx.ErrNoRows) {
		return false, err
	}
	if observation.DraftsOnly && eventStatus != "draft" {
		return false, nil
	}
	if errors.Is(err, pgx.ErrNoRows) && e.Title == "" {
		return false, nil
	}
	var previous collector.Observation
	if len(previousJSON) > 0 {
		if err := json.Unmarshal(previousJSON, &previous); err != nil {
			return false, err
		}
		if observation.CheckedAt.Before(previous.CheckedAt) {
			return false, nil
		}
	}
	if observation.Status == collector.ObservedUnavailable {
		if len(previousJSON) == 0 {
			return false, nil
		}
		_, err := tx.Exec(ctx, `UPDATE event SET import_observation = import_observation ||
			jsonb_build_object('checkError', $2::text, 'failedCheckAt', $3::text) WHERE id = $1`,
			eventID, observation.CheckError, observation.CheckedAt.Format(time.RFC3339))
		return false, err
	}
	if e.Title != "" {
		observation.Title = title.Display
		if title.ReviewReason != "" {
			observation.Issues = append(append([]string(nil), observation.Issues...), title.ReviewReason)
		}
	}

	encoded, err := json.Marshal(observation)
	if err != nil {
		return false, err
	}

	var venueID *int
	if e.VenueName != "" {
		id, err := ensureVenue(ctx, tx, cityID, e.VenueName, e.Address)
		if err != nil {
			return false, err
		}
		venueID = &id
	}
	created := eventID == 0
	if created {
		slug := e.Slug
		if slug == "" {
			slug = "bezkassira-" + e.ExternalID
		}
		err := tx.QueryRow(ctx, `
			INSERT INTO event (title, slug, category_id, city_id, venue_id, price_min, price_max,
				is_free, image_url, source_url, ticket_url, status, source_id, external_id, created_at, updated_at, import_observation, title_review_reason)
			VALUES ($1, $2, $3, $4, $5, $6::numeric, $7::numeric, $8, $9, $10, $11,
				'draft', $12, $13, now(), now(), $14::jsonb, NULLIF($15, ''))
			RETURNING id`, title.Display, slug, categoryID, cityID, venueID,
			e.PriceMin, e.PriceMax, e.IsFree, nullable(e.ImageURL), e.SourceURL, nullable(e.TicketURL),
			sourceID, e.ExternalID, encoded, title.ReviewReason).Scan(&eventID)
		if err != nil {
			return false, err
		}
	} else {
		_, err := tx.Exec(ctx, `
			UPDATE event SET import_observation = $2::jsonb,
				source_url = CASE WHEN source_url IS NULL OR source_url = import_observation->>'url' THEN $3 ELSE source_url END,
				price_min = CASE WHEN $5 THEN $4::numeric ELSE price_min END,
				price_max = CASE WHEN $5 THEN $6::numeric ELSE price_max END,
				is_free = CASE WHEN $5 THEN $8 ELSE is_free END,
				ticket_url = CASE WHEN $5 THEN $7 ELSE ticket_url END,
				updated_at = now()
			WHERE id = $1`, eventID, encoded, observation.URL, e.PriceMin,
			observation.HasConfirmedSession() && (e.PriceMin != nil || observation.SourceName == "CityDog" && e.IsFree), e.PriceMax, nullable(e.TicketURL), e.IsFree)
		if err != nil {
			return false, err
		}
	}

	if !observation.HasConfirmedSession() {
		if observation.Status == collector.ObservedCancelled || observation.Status == collector.ObservedPostponed {
			if _, err := tx.Exec(ctx, `UPDATE event_session SET status = 'cancelled'
				WHERE event_id = $1 AND managed_by_import AND status <> 'cancelled'
				  AND COALESCE(ends_at, starts_at) >= $2`, eventID, observation.CheckedAt); err != nil {
				return false, err
			}
		}
		// A later run must not continually reset a moderator's decision on the same observation.
		if previous.Status != observation.Status || previous.StartsAt != observation.StartsAt || previous.Notice != observation.Notice {
			_, err = tx.Exec(ctx, `UPDATE event SET status = 'draft' WHERE id = $1 AND status = 'published'`, eventID)
		}
		return created, err
	}

	status := "scheduled"
	if observation.Status == collector.ObservedSoldOut {
		status = "sold_out"
	}
	_, err = tx.Exec(ctx, `
		UPDATE event_session SET status = 'cancelled'
		WHERE event_id = $1 AND managed_by_import AND status <> 'cancelled'
		  AND COALESCE(ends_at, starts_at) >= $4
		  AND (starts_at <> $2 OR venue_id IS DISTINCT FROM $3)`,
		eventID, e.StartsAt, venueID, observation.CheckedAt)
	if err != nil {
		return false, err
	}

	previousStart, _ := time.Parse(time.RFC3339, previous.StartsAt)
	titleChanged := previous.Title != "" && previous.Title != observation.Title
	if titleChanged {
		// Existing observations can still contain the full heading from the source.
		previousName := eventtitle.Extract(previous.Title, source).Display
		err = tx.QueryRow(ctx, `SELECT afisha_normalized_title($1) NOT IN
			(afisha_normalized_title($2), afisha_normalized_title($3))`,
			observation.Title, previous.Title, previousName).Scan(&titleChanged)
		if err != nil {
			return false, err
		}
	}
	sessionChanged := !previous.HasConfirmedSession() || !previousStart.Equal(e.StartsAt) || titleChanged
	venueChanged := previous.Venue != observation.Venue || previous.Address != observation.Address
	if !created && (sessionChanged || venueChanged) {
		reopen := sessionChanged || eventStatus != "duplicate"
		_, err = tx.Exec(ctx, `UPDATE event SET
			status = CASE WHEN $3 THEN 'draft' ELSE status END,
			duplicate_of_id = CASE WHEN $3 THEN NULL ELSE duplicate_of_id END,
			venue_id = CASE WHEN NOT EXISTS (SELECT 1 FROM event_session
				WHERE event_id = $1 AND NOT managed_by_import AND status <> 'cancelled')
				THEN $2 ELSE venue_id END WHERE id = $1 AND status IN ('published', 'duplicate', 'draft')`, eventID, venueID, reopen)
		if err != nil {
			return false, err
		}
	}

	_, err = tx.Exec(ctx, `
		INSERT INTO event_session (event_id, starts_at, ends_at, venue_id, ticket_url, status, managed_by_import)
		SELECT $1, $2, $3, $4, $5, $6, true
		WHERE NOT EXISTS (SELECT 1 FROM event_session WHERE event_id = $1 AND NOT managed_by_import AND status <> 'cancelled')
		ON CONFLICT (event_id, starts_at, venue_id) DO UPDATE SET
			ends_at = EXCLUDED.ends_at, ticket_url = EXCLUDED.ticket_url, status = EXCLUDED.status
		WHERE event_session.managed_by_import`,
		eventID, e.StartsAt, e.EndsAt, venueID, nullable(e.TicketURL), status)
	if err != nil || !created || title.ReviewReason != "" {
		return created, err
	}
	if source != "bezkassira" {
		return created, markImportedDuplicate(ctx, tx, eventID)
	}
	if !observation.VerifiedForPublication {
		return created, nil
	}
	var enabled bool
	if err := tx.QueryRow(ctx, `SELECT COALESCE(config @> '{"auto_publish":true}', false)
		FROM source WHERE id = $1 AND slug = 'bezkassira'`, sourceID).Scan(&enabled); err != nil {
		return false, err
	}
	if enabled {
		_, err = applyBezKassiraDecision(ctx, tx, eventID)
		return created, err
	}

	return created, markImportedDuplicate(ctx, tx, eventID)
}
