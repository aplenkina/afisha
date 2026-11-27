package store

import (
	"context"
	"errors"
	"strings"
	"time"

	"github.com/jackc/pgx/v5"
	"github.com/jackc/pgx/v5/pgxpool"

	"afisha/internal/collector"
	"afisha/internal/collector/eventtitle"
)

type Store struct {
	pool *pgxpool.Pool
}

func New(pool *pgxpool.Pool) *Store {
	return &Store{pool: pool}
}

// NormalizeName приводит название города к ключу: нижний регистр, ё -> е.
func NormalizeName(s string) string {
	s = strings.ToLower(strings.TrimSpace(s))
	return strings.ReplaceAll(s, "ё", "е")
}

func (s *Store) Cities(ctx context.Context) (map[string]int, error) {
	rows, err := s.pool.Query(ctx, `SELECT id, name FROM city`)
	if err != nil {
		return nil, err
	}
	defer rows.Close()

	res := map[string]int{}
	for rows.Next() {
		var id int
		var name string
		if err := rows.Scan(&id, &name); err != nil {
			return nil, err
		}
		res[NormalizeName(name)] = id
	}
	return res, rows.Err()
}

// CategoryNames — alias категории -> название для сообщений.
func (s *Store) CategoryNames(ctx context.Context) (map[string]string, error) {
	rows, err := s.pool.Query(ctx, `SELECT alias, name FROM category`)
	if err != nil {
		return nil, err
	}
	defer rows.Close()

	res := map[string]string{}
	for rows.Next() {
		var alias, name string
		if err := rows.Scan(&alias, &name); err != nil {
			return nil, err
		}
		res[alias] = name
	}
	return res, rows.Err()
}

func (s *Store) Categories(ctx context.Context) (map[string]int, error) {
	rows, err := s.pool.Query(ctx, `SELECT id, alias FROM category`)
	if err != nil {
		return nil, err
	}
	defer rows.Close()

	res := map[string]int{}
	for rows.Next() {
		var id int
		var alias string
		if err := rows.Scan(&id, &alias); err != nil {
			return nil, err
		}
		res[alias] = id
	}
	return res, rows.Err()
}

// SourceRow — запись источника из таблицы source.
type SourceRow struct {
	ID        int
	CityID    int
	Name      string
	Slug      string
	URL       string
	Available bool
	Config    string // JSON из source.config, пусто — настроек нет
}

func (s *Store) Sources(ctx context.Context) ([]SourceRow, error) {
	rows, err := s.pool.Query(ctx, `
		SELECT id, city_id, name, slug, source_url, available, COALESCE(config::text, '')
		FROM source ORDER BY id`)
	if err != nil {
		return nil, err
	}
	defer rows.Close()

	var res []SourceRow
	for rows.Next() {
		var r SourceRow
		if err := rows.Scan(&r.ID, &r.CityID, &r.Name, &r.Slug, &r.URL, &r.Available, &r.Config); err != nil {
			return nil, err
		}
		res = append(res, r)
	}
	return res, rows.Err()
}

const maxSummaryRunes = 500

// RecordRun сохраняет итог запуска: его показывает админка в списке источников.
func (s *Store) RecordRun(ctx context.Context, sourceID int, status, summary string) error {
	if r := []rune(summary); len(r) > maxSummaryRunes {
		summary = string(r[:maxSummaryRunes-1]) + "…"
	}
	_, err := s.pool.Exec(ctx, `
		UPDATE source SET last_run_at = now(), last_run_status = $2, last_run_summary = $3
		WHERE id = $1`, sourceID, status, summary)
	return err
}

// LastRunAt — самый поздний запуск среди всех источников; нулевое время, если запусков не было.
func (s *Store) LastRunAt(ctx context.Context) (time.Time, error) {
	var t *time.Time
	if err := s.pool.QueryRow(ctx, `SELECT max(last_run_at) FROM source`).Scan(&t); err != nil {
		return time.Time{}, err
	}
	if t == nil {
		return time.Time{}, nil
	}
	return *t, nil
}

// Save записывает новое событие черновиком или связывает его с опубликованным оригиналом.
// Повторный вызов не создаёт карточек:
// событие ищется по (source_id, external_id), у существующего обновляются только цена и ссылки,
// чтобы не затирать правки модератора. Дубль с новым сеансом возвращается в черновики.
func (s *Store) Save(ctx context.Context, sourceID, cityID, categoryID int, e collector.Event) (created bool, err error) {
	tx, err := s.pool.Begin(ctx)
	if err != nil {
		return false, err
	}
	defer tx.Rollback(ctx)

	// Use the same lock as publication, which checks the saved sessions.
	var source string
	if err = tx.QueryRow(ctx, `SELECT source.slug FROM city CROSS JOIN source
		WHERE city.id = $1 AND source.id = $2 FOR NO KEY UPDATE OF city`, cityID, sourceID).Scan(&source); err != nil {
		return false, err
	}
	title := eventtitle.Extract(e.Title, source)

	if e.Observation != nil {
		created, err := s.saveObservation(ctx, tx, sourceID, cityID, categoryID, source, e, title)
		if err != nil {
			return false, err
		}
		return created, tx.Commit(ctx)
	}

	var venueID *int
	if e.VenueName != "" {
		id, err := ensureVenue(ctx, tx, cityID, e.VenueName, e.Address)
		if err != nil {
			return false, err
		}
		venueID = &id
	}

	var eventID int
	var originalID *int
	err = tx.QueryRow(ctx, `
		INSERT INTO event (title, slug, category_id, city_id, venue_id, price_min, price_max, is_free,
			image_url, source_url, ticket_url, status, source_id, external_id, created_at, updated_at, title_review_reason)
		VALUES ($1, $2, $3, $4, $5, $6::numeric, $7::numeric, $8, $9, $10, $11, 'draft', $12, $13, now(), now(), NULLIF($14, ''))
		ON CONFLICT (source_id, external_id) DO UPDATE SET
			price_min = EXCLUDED.price_min,
			price_max = EXCLUDED.price_max,
			is_free = EXCLUDED.is_free,
			ticket_url = EXCLUDED.ticket_url,
			updated_at = now()
		RETURNING id, duplicate_of_id, (xmax = 0)`,
		title.Display, e.Slug, categoryID, cityID, venueID, e.PriceMin, e.PriceMax, e.IsFree,
		nullable(e.ImageURL), nullable(e.SourceURL), nullable(e.TicketURL), sourceID, e.ExternalID, title.ReviewReason,
	).Scan(&eventID, &originalID, &created)
	if err != nil {
		return false, err
	}

	_, err = tx.Exec(ctx, `
		INSERT INTO event_session (event_id, starts_at, ends_at, venue_id, ticket_url, status)
		VALUES ($1, $2, $3, $4, $5, 'scheduled')
		ON CONFLICT (event_id, starts_at, venue_id) DO NOTHING`,
		eventID, e.StartsAt, e.EndsAt, venueID, nullable(e.TicketURL))
	if err != nil {
		return false, err
	}

	if originalID != nil {
		_, err = tx.Exec(ctx, `
			UPDATE event source
			SET status = 'draft', duplicate_of_id = NULL
			FROM event original
			WHERE source.id = $1 AND original.id = source.duplicate_of_id
			  AND NOT afisha_duplicate_session_starts_match(source.id, original.id)`, eventID)
		if err != nil {
			return false, err
		}
	}
	if created && title.ReviewReason == "" {
		if err := markImportedDuplicate(ctx, tx, eventID); err != nil {
			return false, err
		}
	}

	return created, tx.Commit(ctx)
}

func markImportedDuplicate(ctx context.Context, tx pgx.Tx, eventID int) error {
	_, err := tx.Exec(ctx, `
		UPDATE event SET status = 'duplicate', duplicate_of_id = original.id, updated_at = now()
		FROM (SELECT id FROM afisha_duplicate_candidates($1, true) ORDER BY id LIMIT 1) original
		WHERE event.id = $1 AND event.status = 'draft'`, eventID)
	return err
}

func ensureVenue(ctx context.Context, tx pgx.Tx, cityID int, name, address string) (int, error) {
	var id int
	err := tx.QueryRow(ctx, `SELECT id FROM venue WHERE city_id = $1 AND name = $2`, cityID, name).Scan(&id)
	if err == nil {
		return id, nil
	}
	if !errors.Is(err, pgx.ErrNoRows) {
		return 0, err
	}

	err = tx.QueryRow(ctx, `
		INSERT INTO venue (city_id, name, address, created_at)
		VALUES ($1, $2, $3, now())
		RETURNING id`, cityID, name, address).Scan(&id)
	return id, err
}

func nullable(s string) *string {
	if s == "" {
		return nil
	}
	return &s
}
