<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261121230000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Общие правила дублей и автопубликация проверенных карточек BezKassira';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE FUNCTION afisha_normalized_title(value text) RETURNS text
            LANGUAGE sql IMMUTABLE STRICT PARALLEL SAFE AS
            'SELECT trim(regexp_replace(lower(value), ''[[:space:]]+'', '' '', ''g''))'
            SQL);

        $this->addSql(<<<'SQL'
            CREATE FUNCTION afisha_duplicate_candidates(event_id_arg integer, published_only boolean)
            RETURNS TABLE (id integer, title varchar, status varchar, starts_at timestamptz)
            LANGUAGE sql STABLE AS $$
                SELECT candidate.id, candidate.title, candidate.status, source_schedule.starts_at
                FROM event original
                JOIN event candidate ON candidate.city_id = original.city_id AND candidate.id <> original.id
                JOIN LATERAL (
                    SELECT MIN(se.starts_at) AS starts_at
                    FROM event_session se
                    WHERE se.event_id = original.id AND se.status <> 'cancelled'
                ) source_schedule ON source_schedule.starts_at IS NOT NULL
                WHERE original.id = event_id_arg
                  AND candidate.status IN ('draft', 'published')
                  AND (original.status <> 'published' OR candidate.status = 'published')
                  AND (NOT published_only OR candidate.status = 'published')
                  AND afisha_normalized_title(original.title) <> ''
                  AND afisha_normalized_title(candidate.title) = afisha_normalized_title(original.title)
                  AND NOT EXISTS (
                      SELECT 1 FROM event_session current_session
                      WHERE current_session.event_id = original.id
                        AND current_session.status <> 'cancelled'
                        AND NOT EXISTS (
                            SELECT 1 FROM event_session other
                            WHERE other.event_id = candidate.id
                              AND other.status <> 'cancelled'
                              AND other.starts_at = current_session.starts_at
                              AND COALESCE(other.venue_id, candidate.venue_id)
                                  = COALESCE(current_session.venue_id, original.venue_id)
                        )
                  )
                ORDER BY (candidate.status = 'published') DESC, candidate.id
                LIMIT 10
            $$
            SQL);

        $this->addSql(<<<'SQL'
            CREATE FUNCTION afisha_bezkassira_decision(event_id_arg integer)
            RETURNS TABLE (decision text, original_id integer)
            LANGUAGE sql STABLE AS $$
                WITH checked AS (
                    SELECT e.*, CASE
                        WHEN observation->>'status' NOT IN ('scheduled', 'sold_out')
                          OR jsonb_exists(observation, 'issues') OR jsonb_exists(observation, 'checkError')
                          OR NOT COALESCE((observation->>'verifiedForPublication')::boolean, false)
                          OR NOT COALESCE(pg_input_is_valid(observation->>'checkedAt', 'timestamp with time zone'), false)
                          OR NOT COALESCE(pg_input_is_valid(observation->>'startsAt', 'timestamp with time zone'), false)
                        THEN 'review'
                        WHEN (observation->>'checkedAt')::timestamptz < now() - interval '30 hours'
                          OR (observation->>'checkedAt')::timestamptz > now() + interval '5 minutes'
                          OR btrim(e.title) = '' OR e.title IS DISTINCT FROM observation->>'title'
                          OR e.source_url IS DISTINCT FROM observation->>'url'
                          OR NOT EXISTS (
                              SELECT 1 FROM event_session se
                              WHERE se.event_id = e.id AND se.status <> 'cancelled'
                                AND se.starts_at >= now()
                          )
                          OR EXISTS (
                              SELECT 1 FROM event_session old_session
                              WHERE old_session.event_id = e.id AND old_session.managed_by_import
                                AND old_session.status = 'cancelled'
                          )
                          OR EXISTS (
                              SELECT 1 FROM event_session se
                              LEFT JOIN venue v ON v.id = COALESCE(se.venue_id, e.venue_id)
                              WHERE se.event_id = e.id AND se.status <> 'cancelled'
                                AND (NOT se.managed_by_import
                                  OR se.starts_at <> (observation->>'startsAt')::timestamptz
                                  OR v.id IS NULL OR btrim(v.name) = '' OR btrim(v.address) = '')
                          )
                        THEN 'review'
                        ELSE 'eligible'
                        END AS quality
                    FROM event e
                    JOIN source s ON s.id = e.source_id AND s.slug = 'bezkassira'
                    CROSS JOIN LATERAL (SELECT e.import_observation AS observation) evidence
                    WHERE e.id = event_id_arg AND e.status = 'draft'
                )
                SELECT CASE
                    WHEN c.quality = 'review' THEN 'review'
                    WHEN duplicate.id IS NOT NULL THEN 'duplicate'
                    WHEN EXISTS (
                        -- A shared announcement or ticket link is evidence of the same session.
                        -- Similar names alone must not conflate separate performances.
                        SELECT 1 FROM event other
                        JOIN event_session se ON se.event_id = other.id
                        WHERE other.city_id = c.city_id
                          AND other.id <> c.id
                          AND other.source_id IS DISTINCT FROM c.source_id
                          AND other.status IN ('draft', 'published', 'duplicate')
                          AND se.status <> 'cancelled'
                          AND EXISTS (
                              SELECT 1
                              FROM unnest(ARRAY[other.source_url, other.ticket_url, se.ticket_url]) AS linked(url)
                              WHERE regexp_replace(split_part(split_part(linked.url, '?', 1), '#', 1), '(/buy)?/*$', '')
                                  = regexp_replace(c.source_url, '/+$', '')
                          )
                    ) THEN 'conflict'
                    ELSE 'published'
                END,
                CASE WHEN c.quality = 'eligible' THEN duplicate.id END
                FROM checked c
                LEFT JOIN LATERAL (
                    SELECT d.id FROM afisha_duplicate_candidates(c.id, true) d
                    ORDER BY d.id LIMIT 1
                ) duplicate ON c.quality = 'eligible'
            $$
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException();
    }
}
