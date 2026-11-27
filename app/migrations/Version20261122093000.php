<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261122093000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Добавляет CityDog как источник кандидатов на публикацию в Минске';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            INSERT INTO source (city_id, name, slug, source_url, available, created_at, config)
            SELECT city.id, 'CityDog', 'citydog', 'https://citydog.io', false, now(), '{}'::jsonb
            FROM city
            WHERE city.slug = 'minsk'
              AND NOT EXISTS (SELECT 1 FROM source WHERE source.slug = 'citydog')
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE source SET available = false WHERE slug = 'citydog'");
    }
}
