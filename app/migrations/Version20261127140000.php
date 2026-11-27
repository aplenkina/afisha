<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261127140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Убирает пунктуацию и пробелы из ключа дублей и добавляет причину проверки названия';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE event ADD title_review_reason TEXT DEFAULT NULL');
        $this->addSql(<<<'SQL'
            CREATE OR REPLACE FUNCTION afisha_normalized_title(value text) RETURNS text
            LANGUAGE sql IMMUTABLE STRICT PARALLEL SAFE AS $$
                SELECT regexp_replace(lower(normalize(value)), '[^[:alnum:]]+', '', 'g')
            $$
            SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_event_city_normalized_title ON event (city_id, afisha_normalized_title(title))
            WHERE status IN ('draft', 'published')
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_event_city_normalized_title');
        $this->addSql('ALTER TABLE event DROP title_review_reason');
        $this->addSql(<<<'SQL'
            CREATE OR REPLACE FUNCTION afisha_normalized_title(value text) RETURNS text
            LANGUAGE sql IMMUTABLE STRICT PARALLEL SAFE AS $$
                SELECT trim(regexp_replace(lower(value), '[[:space:]]+', ' ', 'g'))
            $$
            SQL);
    }
}
