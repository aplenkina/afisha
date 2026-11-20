<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261119161500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Обновляет страницы Ticketpro и включает сбор с карточек событий';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            UPDATE source
            SET source_url = 'https://ticketpro.by',
                config = COALESCE(config, '{}'::jsonb) || '{
                    "pages": ["/ru/bilety-na-koncert", "/ru/bilety-v-teatr", "/ru/bilety-na-sportivnye-meropriyatiya"],
                    "categories": {"bilety-na-koncert": "concert", "bilety-v-teatr": "theatre", "bilety-na-sportivnye-meropriyatiya": "sport"},
                    "detail_path_prefix": "/ru/event/"
                }'::jsonb
            WHERE slug = 'ticketpro'
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException();
    }
}
