BEGIN;

INSERT INTO city (name, slug, available, created_at)
VALUES ('Минск', 'minsk', true, now())
ON CONFLICT (slug) DO NOTHING;

INSERT INTO category (name, alias, position, available, created_at)
SELECT defaults.name, defaults.alias, defaults.position, true, now()
FROM (VALUES ('Концерты', 'concert', 10), ('Театр', 'theatre', 20), ('Спорт', 'sport', 30)) AS defaults(name, alias, position)
WHERE NOT EXISTS (SELECT 1 FROM category WHERE alias = defaults.alias);

INSERT INTO source (city_id, name, slug, source_url, available, created_at, config)
SELECT city.id, defaults.name, defaults.slug, defaults.url, true, now(), defaults.config::jsonb
FROM city
CROSS JOIN (VALUES
    ('Ticketpro', 'ticketpro', 'https://ticketpro.by', '{
        "pages": ["/ru/bilety-na-koncert", "/ru/bilety-v-teatr", "/ru/bilety-na-sportivnye-meropriyatiya"],
        "categories": {"bilety-na-koncert": "concert", "bilety-v-teatr": "theatre", "bilety-na-sportivnye-meropriyatiya": "sport"},
        "detail_path_prefix": "/ru/event/"
    }'),
    ('Hubl', 'hubl', 'https://hubl.by', '{
        "pages": ["/minsk/koncerty", "/minsk/teatr"],
        "categories": {"minsk/koncerty": "concert", "minsk/teatr": "theatre"}
    }')
) AS defaults(name, slug, url, config)
WHERE city.slug = 'minsk'
  AND NOT EXISTS (SELECT 1 FROM source WHERE slug = defaults.slug);

COMMIT;
