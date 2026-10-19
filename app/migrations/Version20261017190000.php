<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261017190000 extends AbstractMigration
{
    private const string TICKETPRO_CONFIG = '{"pages": ["/", "/bilety-na-koncert/", "/bilety-v-teatr/", "/bilety-na-sportivnye-meropriyatiya/", "/obuchenie/"], "categories": {"bilety-na-koncert": "concert", "bilety-v-teatr": "theatre", "bilety-na-sportivnye-meropriyatiya": "sport", "obuchenie": "workshop"}}';

    public function getDescription(): string
    {
        return 'source.config: настройки универсального сборщика schema.org/Event; Ticketpro переезжает на него';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE source ADD config JSONB DEFAULT NULL');

        $config = $this->connection->quote(self::TICKETPRO_CONFIG);

        $this->addSql("UPDATE source SET config = $config::jsonb WHERE slug = 'ticketpro' AND config IS NULL");
        $this->addSql(<<<SQL
            INSERT INTO source (city_id, name, slug, source_url, available, created_at, config)
            SELECT c.id, 'Ticketpro', 'ticketpro', 'https://www.ticketpro.by', true, now(), $config::jsonb
            FROM city c
            WHERE c.slug = 'minsk' AND NOT EXISTS (SELECT 1 FROM source WHERE slug = 'ticketpro')
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE source DROP config');
    }
}
