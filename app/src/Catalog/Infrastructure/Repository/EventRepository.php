<?php

namespace App\Catalog\Infrastructure\Repository;

use App\Catalog\Application\ShowCategoryEvents\CategoryEventView;
use App\Catalog\Domain\Event;
use App\Catalog\Domain\EventNotFound;
use App\Catalog\Domain\EventSession;
use App\Catalog\Domain\EventStatus;
use App\Shared\Infrastructure\Doctrine\AbstractDoctrineRepository;

readonly class EventRepository extends AbstractDoctrineRepository implements EventRepositoryInterface
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
    public function findList(?EventStatus $status = null, int $limit = 200, ?int $createdBy = null, ?int $categoryId = null, bool $onlyUnpublished = false): array
    {
        $criteria = [];
        if (null !== $status) {
            $criteria['status'] = $status;
        } elseif ($onlyUnpublished) {
            $criteria['status'] = array_values(array_map(
                static fn (EventStatus $item): string => $item->value,
                array_filter(EventStatus::cases(), static fn (EventStatus $item): bool => EventStatus::Published !== $item),
            ));
        }
        if (null !== $createdBy) {
            $criteria['createdBy'] = $createdBy;
        }
        if (null !== $categoryId) {
            $criteria['categoryId'] = $categoryId;
        }

        return $this->repository(Event::class)->findBy($criteria, ['id' => 'DESC'], $limit);
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
                       -- Older databases may not have import_observation.
                       CASE WHEN CAST(:category AS integer) IS NOT NULL AND e.image_url IS NULL
                            THEN to_jsonb(e)->'import_observation'->>'originalUrl'
                       END AS instagram_post_url,
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
            instagramPostUrl: Event::instagramPostUrlFrom($row['instagram_post_url']) ?? Event::instagramPostUrlFrom($row['source_url']),
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
                SELECT candidate.id, candidate.title, candidate.status, source_schedule.starts_at
                FROM event original
                JOIN event candidate ON candidate.city_id = original.city_id AND candidate.id <> original.id
                JOIN LATERAL (
                    SELECT MIN(starts_at) AS starts_at
                    FROM event_session
                    WHERE event_id = original.id AND status <> 'cancelled'
                ) source_schedule ON source_schedule.starts_at IS NOT NULL
                WHERE original.id = :event
                  AND candidate.status IN ('draft', 'published')
                  AND (original.status <> 'published' OR candidate.status = 'published')
                  AND (NOT CAST(:published_only AS boolean) OR candidate.status = 'published')
                  AND trim(regexp_replace(lower(original.title), '[[:space:]]+', ' ', 'g')) <> ''
                  AND trim(regexp_replace(lower(candidate.title), '[[:space:]]+', ' ', 'g'))
                      = trim(regexp_replace(lower(original.title), '[[:space:]]+', ' ', 'g'))
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
                SQL,
            ['event' => $eventId, 'published_only' => $publishedOnly ? 'true' : 'false'],
        );
    }

    #[\Override]
    public function findPublishedDuplicateSeedIds(): array
    {
        $ids = $this->entityManager->getConnection()->fetchFirstColumn(<<<'SQL'
            WITH candidates AS (
                SELECT id, COUNT(*) OVER (
                    PARTITION BY city_id, trim(regexp_replace(lower(title), '[[:space:]]+', ' ', 'g'))
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
