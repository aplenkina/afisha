<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261127133000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Сравнивает дубли по названию, городу и времени сеансов независимо от площадки';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE OR REPLACE FUNCTION afisha_normalized_title(value text) RETURNS text
            LANGUAGE sql IMMUTABLE STRICT PARALLEL SAFE AS
            'SELECT trim(regexp_replace(lower(value), ''[[:space:]]+'', '' '', ''g''))'
            SQL);

        $this->updateSessionMatcher();

        $this->addSql(<<<'SQL'
            CREATE OR REPLACE FUNCTION afisha_duplicate_candidates(event_id_arg integer, published_only boolean)
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
                  AND afisha_duplicate_session_starts_match(original.id, candidate.id)
                ORDER BY (candidate.status = 'published') DESC, candidate.id
                LIMIT 10
            $$
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->updateSessionMatcher(<<<'SQL'
                            AND COALESCE(other.venue_id, (SELECT venue_id FROM event WHERE id = target_event_id))
                                = COALESCE(current_session.venue_id, (SELECT venue_id FROM event WHERE id = source_event_id))
            SQL);
    }

    private function updateSessionMatcher(string $venueCondition = ''): void
    {
        $this->addSql(\sprintf(<<<'SQL'
            CREATE OR REPLACE FUNCTION afisha_duplicate_session_starts_match(source_event_id integer, target_event_id integer)
            RETURNS boolean
            LANGUAGE sql STABLE STRICT AS $$
                SELECT NOT EXISTS (
                    SELECT 1 FROM event_session current_session
                    WHERE current_session.event_id = source_event_id
                      AND current_session.status <> 'cancelled'
                      AND NOT EXISTS (
                          SELECT 1 FROM event_session other
                          WHERE other.event_id = target_event_id
                            AND other.status <> 'cancelled'
                            AND other.starts_at = current_session.starts_at
            %s
                      )
                )
            $$
            SQL, $venueCondition));
    }
}
