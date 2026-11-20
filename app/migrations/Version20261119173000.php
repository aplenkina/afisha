<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261119173000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Добавляет источник Hubl для концертов и театра в Минске';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            INSERT INTO source (city_id, name, slug, source_url, available, created_at, config)
            SELECT c.id, 'Hubl', 'hubl', 'https://hubl.by', true, now(), '{
                "pages": ["/minsk/koncerty", "/minsk/teatr"],
                "categories": {"minsk/koncerty": "concert", "minsk/teatr": "theatre"}
            }'::jsonb
            FROM city c
            WHERE c.slug = 'minsk' AND NOT EXISTS (SELECT 1 FROM source WHERE slug = 'hubl')
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException();
    }
}
