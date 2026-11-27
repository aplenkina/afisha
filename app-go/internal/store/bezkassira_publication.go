package store

import (
	"context"
	"errors"

	"github.com/jackc/pgx/v5"
)

type BezKassiraPublication struct {
	Published int
	Duplicate int
	Review    int
	Conflict  int
}

func applyBezKassiraDecision(ctx context.Context, tx pgx.Tx, eventID int) (string, error) {
	var decision string
	var originalID *int
	err := tx.QueryRow(ctx, "SELECT decision, original_id FROM afisha_bezkassira_decision($1)", eventID).
		Scan(&decision, &originalID)
	if errors.Is(err, pgx.ErrNoRows) {
		return "", nil
	}
	if err != nil {
		return "", err
	}
	if decision != "published" && decision != "duplicate" {
		return decision, nil
	}
	if _, err := tx.Exec(ctx, `
		UPDATE event SET status = $2, duplicate_of_id = $3, updated_at = now()
		WHERE id = $1 AND status = 'draft'`, eventID, decision, originalID); err != nil {
		return "", err
	}
	if originalID != nil {
		// Manual merges preserve the same invariant: all links point to the published original.
		if _, err := tx.Exec(ctx, `UPDATE event SET duplicate_of_id = $2
			WHERE duplicate_of_id = $1 AND status = 'duplicate'`, eventID, *originalID); err != nil {
			return "", err
		}
	}
	return decision, nil
}

// DraftBezKassiraURLs selects only previously imported, upcoming draft cards for the batch recheck.
func (s *Store) DraftBezKassiraURLs(ctx context.Context, sourceID int) ([]string, error) {
	rows, err := s.pool.Query(ctx, `
		SELECT e.import_observation->>'url' AS url
		FROM event e WHERE e.source_id = $1 AND e.status = 'draft'
		  AND e.import_observation->>'url' IS NOT NULL
		  AND EXISTS (SELECT 1 FROM event_session se
			WHERE se.event_id = e.id AND se.status <> 'cancelled' AND se.starts_at >= now())
		ORDER BY e.id`, sourceID)
	if err != nil {
		return nil, err
	}
	return pgx.CollectRows(rows, pgx.RowTo[string])
}

// PublishBezKassiraDrafts also runs in a rolled-back transaction so the preview accounts
// for duplicates between candidates in the same batch.
func (s *Store) PublishBezKassiraDrafts(ctx context.Context, apply bool) (BezKassiraPublication, error) {
	var result BezKassiraPublication
	tx, err := s.pool.Begin(ctx)
	if err != nil {
		return result, err
	}
	defer tx.Rollback(ctx)

	var sourceID, cityID int
	if err := tx.QueryRow(ctx, `SELECT id, city_id FROM source WHERE slug = 'bezkassira'`).
		Scan(&sourceID, &cityID); err != nil {
		return result, err
	}
	if _, err := tx.Exec(ctx, `SELECT id FROM city WHERE id = $1 FOR NO KEY UPDATE`, cityID); err != nil {
		return result, err
	}
	rows, err := tx.Query(ctx, `SELECT id FROM event WHERE source_id = $1 AND city_id = $2 AND status = 'draft'
		ORDER BY id FOR UPDATE`, sourceID, cityID)
	if err != nil {
		return result, err
	}
	ids, err := pgx.CollectRows(rows, pgx.RowTo[int])
	if err != nil {
		return result, err
	}
	for _, id := range ids {
		decision, err := applyBezKassiraDecision(ctx, tx, id)
		if err != nil {
			return result, err
		}
		switch decision {
		case "published":
			result.Published++
		case "duplicate":
			result.Duplicate++
		case "review":
			result.Review++
		case "conflict":
			result.Conflict++
		}
	}
	if !apply {
		return result, tx.Rollback(ctx)
	}
	if _, err := tx.Exec(ctx, `UPDATE source SET config = COALESCE(config, '{}'::jsonb) ||
		jsonb_build_object('auto_publish', true) WHERE id = $1`, sourceID); err != nil {
		return result, err
	}
	return result, tx.Commit(ctx)
}
