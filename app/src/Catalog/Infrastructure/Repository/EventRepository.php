<?php

namespace App\Catalog\Infrastructure\Repository;

use App\Catalog\Application\EventEditing\ImportedEventComparisons;
use App\Catalog\Application\ShowCategoryEvents\CategoryEventView;
use App\Catalog\Domain\Event;
use App\Catalog\Domain\EventNotFound;
use App\Catalog\Domain\EventSession;
use App\Catalog\Domain\EventStatus;
use App\Shared\Infrastructure\Doctrine\AbstractDoctrineRepository;

readonly class EventRepository extends AbstractDoctrineRepository implements EventRepositoryInterface, ImportedEventComparisons
{
    #[\Override]
    public function save(Event $event): void
    {
        $this->add($event);
    }

    #[\Override]
    public function delete(Event $event): void
    {
        $this->remove($event);
    }

    #[\Override]
    public function findUpcomingPublishedByCategory(int $categoryId, \DateTimeImmutable $now, int $limit = 200): array
    {
        return $this->findPublicEvents($now, $limit, categoryId: $categoryId);
    }

    #[\Override]
    public function findCalendarEvents(\DateTimeImmutable $from, ?\DateTimeImmutable $until, ?int $cityId, bool $onlyFree, ?int $categoryId = null, string $search = '', int $limit = 200, int $offset = 0): array
    {
        return $this->findPublicEvents($from, $limit, categoryId: $categoryId, until: $until, cityId: $cityId, onlyFree: $onlyFree, search: $search, perDay: true, onlyAvailable: true, offset: $offset);
    }

    #[\Override]
    public function findDigestSessions(\DateTimeImmutable $from, \DateTimeImmutable $until, int $cityId, bool $onlyFree, ?int $categoryId = null): array
    {
        return $this->findPublicEvents($from, 200, categoryId: $categoryId, until: $until, cityId: $cityId, onlyFree: $onlyFree, allSessions: true, onlyAvailable: true);
    }

    private function findPublicEvents(\DateTimeImmutable $now, int $limit, ?int $categoryId = null, ?\DateTimeImmutable $until = null, ?int $cityId = null, bool $onlyFree = false, string $search = '', bool $allSessions = false, bool $perDay = false, bool $onlyAvailable = false, int $offset = 0): array
    {
        $rows = $this->entityManager->getConnection()->fetchAllAssociative(
            <<<'SQL'
                SELECT e.id, e.title, e.slug, e.price_min, e.price_max, e.is_free,
                       COALESCE(s.ticket_url, e.ticket_url) AS ticket_url, e.source_url,
                       e.image_url, category.name AS category_name,
                       s.starts_at, s.ends_at, v.name AS venue_name, v.address, c.name AS city_name
                FROM event e
                JOIN city c ON c.id = e.city_id
                JOIN category ON category.id = e.category_id
                LEFT JOIN category parent_category ON parent_category.id = category.parent_id
                JOIN LATERAL (
                    SELECT DISTINCT ON (session_key, session_day) starts_at, ends_at, venue_id, ticket_url
                    FROM (
                        SELECT id, starts_at, ends_at, venue_id, ticket_url,
                               CASE WHEN CAST(:all_sessions AS boolean) THEN id END AS session_key,
                               CASE WHEN CAST(:per_day AS boolean) THEN (starts_at AT TIME ZONE 'Europe/Minsk')::date END AS session_day
                        FROM event_session
                        WHERE event_id = e.id
                          AND status <> 'cancelled'
                          AND starts_at >= :now
                          AND (CAST(:until AS timestamptz) IS NULL OR starts_at < :until)
                    ) AS eligible_sessions
                    ORDER BY session_key, session_day, starts_at, id
                ) s ON true
                LEFT JOIN venue v ON v.id = COALESCE(s.venue_id, e.venue_id)
                WHERE (CAST(:category AS integer) IS NULL OR e.category_id = :category OR category.parent_id = :category)
                  AND (CAST(:city AS integer) IS NULL OR e.city_id = :city)
                  AND (NOT CAST(:only_free AS boolean) OR e.is_free)
                  AND (NOT CAST(:only_available AS boolean) OR (c.available AND category.available))
                  AND (category.parent_id IS NULL OR (category.available AND parent_category.available))
                  AND e.status = :status
                  AND strpos(lower(e.title), lower(:search)) > 0
                ORDER BY s.starts_at, e.id
                LIMIT :limit OFFSET :offset
                SQL,
            [
                'now' => $now->format('Y-m-d H:i:sP'),
                'category' => $categoryId,
                'city' => $cityId,
                'until' => $until?->format('Y-m-d H:i:sP'),
                'only_free' => $onlyFree ? 'true' : 'false',
                'status' => EventStatus::Published->value,
                'search' => $search,
                'all_sessions' => $allSessions ? 'true' : 'false',
                'per_day' => $perDay ? 'true' : 'false',
                'only_available' => $onlyAvailable ? 'true' : 'false',
                'limit' => $limit,
                'offset' => $offset,
            ],
        );

        return array_map(static fn (array $row): CategoryEventView => new CategoryEventView(
            id: (int) $row['id'],
            title: $row['title'],
            slug: $row['slug'],
            startsAt: new \DateTimeImmutable($row['starts_at']),
            endsAt: null !== $row['ends_at'] ? new \DateTimeImmutable($row['ends_at']) : null,
            cityName: $row['city_name'],
            venueName: $row['venue_name'],
            address: $row['address'],
            priceMin: $row['price_min'],
            priceMax: $row['price_max'],
            isFree: (bool) $row['is_free'],
            ticketUrl: self::httpUrlOrNull($row['ticket_url']),
            sourceUrl: self::httpUrlOrNull($row['source_url']),
            imageUrl: self::httpUrlOrNull($row['image_url']),
            categoryName: $row['category_name'],
        ), $rows);
    }

    /** Ссылки приходят от сборщиков, на сайт пускаем только http(s). */
    private static function httpUrlOrNull(?string $url): ?string
    {
        return null !== $url && 1 === preg_match('#^https?://#i', $url) ? $url : null;
    }

    #[\Override]
    public function findDuplicateCandidates(int $eventId, bool $publishedOnly = false): array
    {
        return $this->entityManager->getConnection()->fetchAllAssociative(
            <<<'SQL'
                SELECT id, title, status, starts_at FROM afisha_duplicate_candidates(:event, CAST(:published_only AS boolean))
                ORDER BY (status = 'published') DESC, id
                SQL,
            ['event' => $eventId, 'published_only' => $publishedOnly ? 'true' : 'false'],
        );
    }

    #[\Override]
    public function forEvent(Event $event): array
    {
        if (null === $event->getImportObservation()) {
            return [];
        }

        $eventId = $event->getId();

        return $this->entityManager->getConnection()->fetchAllAssociative(<<<'SQL'
            SELECT candidate.id, candidate.title, candidate.source_url, candidate.ticket_url,
                candidate.price_min, candidate.price_max, candidate.updated_at,
                COALESCE(source.name, 'Редакция') AS source_name,
                se.starts_at, se.status AS session_status, venue.name AS venue, venue.address
            FROM event imported
            CROSS JOIN LATERAL (
                SELECT CASE WHEN pg_input_is_valid(imported.import_observation->>'startsAt', 'timestamp with time zone')
                    THEN (imported.import_observation->>'startsAt')::timestamptz END AS starts_at
            ) reference
            JOIN event candidate ON candidate.city_id = imported.city_id
                AND candidate.source_id IS DISTINCT FROM imported.source_id
                AND (
                    afisha_normalized_title(candidate.title) = afisha_normalized_title(imported.title)
                    OR (imported.import_observation->>'sourceName' = 'CityDog'
                        AND NULLIF(imported.ticket_url, '') = candidate.ticket_url)
                )
            JOIN event_session se ON se.event_id = candidate.id
            LEFT JOIN venue ON venue.id = COALESCE(se.venue_id, candidate.venue_id)
            LEFT JOIN source ON source.id = candidate.source_id
            WHERE imported.id = :event
              AND candidate.status IN ('draft', 'published', 'duplicate')
              AND candidate.source_url ~ '^https?://'
              AND se.starts_at BETWEEN reference.starts_at - interval '7 days' AND reference.starts_at + interval '7 days'
            ORDER BY abs(extract(epoch FROM se.starts_at - reference.starts_at)), candidate.id
            LIMIT 8
            SQL, ['event' => $eventId]);
    }

    #[\Override]
    public function findPublishedDuplicateSeedIds(): array
    {
        $ids = $this->entityManager->getConnection()->fetchFirstColumn(<<<'SQL'
            WITH candidates AS (
                SELECT id, COUNT(*) OVER (
                    PARTITION BY city_id, afisha_normalized_title(title)
                ) AS same_title
                FROM event WHERE status = 'published'
            )
            SELECT candidates.id FROM candidates
            WHERE same_title > 1 AND EXISTS (
                SELECT 1 FROM event_session
                WHERE event_id = candidates.id AND status <> 'cancelled' AND starts_at >= now()
            )
            ORDER BY candidates.id DESC
            SQL);

        return array_map('intval', $ids);
    }

    #[\Override]
    public function findDuplicatesOf(Event $event): array
    {
        return $this->repository(Event::class)->findBy(['duplicateOf' => $event]);
    }

    #[\Override]
    public function existsByCity(int $cityId): bool
    {
        return $this->repository(Event::class)->count(['cityId' => $cityId]) > 0;
    }

    #[\Override]
    public function existsByVenue(int $venueId): bool
    {
        $inEvents = $this->repository(Event::class)->count(['venueId' => $venueId]) > 0;

        return $inEvents || $this->repository(EventSession::class)->count(['venueId' => $venueId]) > 0;
    }

    #[\Override]
    public function existsBySource(int $sourceId): bool
    {
        return $this->repository(Event::class)->count(['sourceId' => $sourceId]) > 0;
    }

    #[\Override]
    public function findById(int $id): Event
    {
        return $this->repository(Event::class)->find($id) ?? throw new EventNotFound($id);
    }

    #[\Override]
    public function findBySlug(string $slug): Event
    {
        return $this->repository(Event::class)->findOneBy(['slug' => $slug]) ?? throw new EventNotFound($slug);
    }

    #[\Override]
    public function existsBySlug(string $slug): bool
    {
        return $this->repository(Event::class)->count(['slug' => $slug]) > 0;
    }

    #[\Override]
    public function existsBySourceExternalId(int $sourceId, string $externalId): bool
    {
        return $this->repository(Event::class)->count(['sourceId' => $sourceId, 'externalId' => $externalId]) > 0;
    }
}
