<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261121220000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Добавляет источник BezKassira и данные проверки импортированных сеансов';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE event ADD import_observation JSONB DEFAULT NULL');
        $this->addSql('ALTER TABLE event_session ADD managed_by_import BOOLEAN NOT NULL DEFAULT false');

        $this->addSql("INSERT INTO city (name, slug, available, created_at) VALUES ('Минск', 'minsk', true, now()) ON CONFLICT (slug) DO NOTHING");

        $this->addSql(<<<'SQL'
            INSERT INTO category (name, alias, position, available, created_at)
            SELECT defaults.name, defaults.alias, defaults.position, true, now()
            FROM (VALUES
                ('Концерты', 'concert', 10), ('Театр', 'theatre', 20), ('Спорт', 'sport', 30),
                ('Кино', 'cinema', 40), ('Вечеринки', 'party', 50), ('Выставки', 'exhibition', 60),
                ('Детям', 'family', 70), ('Обучение', 'workshop', 80), ('Культура', 'culture', 90),
                ('Экскурсии', 'tour', 100), ('Деловые события', 'business', 110)
            ) AS defaults(name, alias, position)
            WHERE NOT EXISTS (SELECT 1 FROM category WHERE category.alias = defaults.alias)
            SQL);

        $this->addSql(<<<'SQL'
            INSERT INTO source (city_id, name, slug, source_url, available, created_at, config)
            SELECT city.id, 'BezKassira', 'bezkassira', 'https://bezkassira.by', false, now(), '{
                "pages": ["/events/", "/events/concert-minsk/", "/events/teatry-minsk/",
                    "/events/party-minsk/", "/events/kino-minsk/", "/events/sport_event-minsk/",
                    "/events/exhibition-minsk/", "/events/dlya_detey-minsk/", "/events/new_year-minsk/",
                    "/events/education_and_development-minsk/", "/events/iskusstvo_i_kultura-minsk/",
                    "/events/ekskursii_i_puteshestviya-minsk/", "/events/biznes-minsk/",
                    "/events/it_i_internet-minsk/", "/events/psihologiya_i_samopoznanie-minsk/"],
                "categories": {
                    "events/concert-minsk": "concert", "events/teatry-minsk": "theatre",
                    "events/party-minsk": "party", "events/kino-minsk": "cinema",
                    "events/sport_event-minsk": "sport", "events/exhibition-minsk": "exhibition",
                    "events/dlya_detey-minsk": "family", "events/new_year-minsk": "family",
                    "events/education_and_development-minsk": "workshop", "events/iskusstvo_i_kultura-minsk": "culture",
                    "events/ekskursii_i_puteshestviya-minsk": "tour", "events/biznes-minsk": "business",
                    "events/it_i_internet-minsk": "business", "events/psihologiya_i_samopoznanie-minsk": "workshop"
                }
            }'::jsonb
            FROM city
            WHERE city.slug = 'minsk'
              AND NOT EXISTS (SELECT 1 FROM source WHERE source.slug = 'bezkassira')
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException();
    }
}
