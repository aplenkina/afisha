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
    public function findList(?EventStatus $status = null, int $limit = 200, ?int $createdBy = null, ?int $categoryId = null): array
    {
        $criteria = [];
        if (null !== $status) {
            $criteria['status'] = $status;
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
        // Сеанс считаем не прошедшим, пока не кончился (или первые 3 часа, если конец не указан).
        $rows = $this->entityManager->getConnection()->fetchAllAssociative(
            <<<'SQL'
                SELECT e.id, e.title, e.slug, e.price_min, e.price_max, e.is_free, e.ticket_url, e.source_url,
                       s.starts_at, s.ends_at, v.name AS venue_name, v.address, c.name AS city_name
                FROM event e
                JOIN city c ON c.id = e.city_id
                JOIN LATERAL (
                    SELECT starts_at, ends_at, venue_id
                    FROM event_session
                    WHERE event_id = e.id
                      AND status <> 'cancelled'
                      AND COALESCE(ends_at, starts_at + interval '3 hours') >= :now
                    ORDER BY starts_at
                    LIMIT 1
                ) s ON true
                LEFT JOIN venue v ON v.id = COALESCE(s.venue_id, e.venue_id)
                WHERE e.category_id = :category AND e.status = :status
                ORDER BY s.starts_at, e.id
                LIMIT :limit
                SQL,
            [
                'now' => $now->format('Y-m-d H:i:sP'),
                'category' => $categoryId,
                'status' => EventStatus::Published->value,
                'limit' => $limit,
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
        ), $rows);
    }

    /** Ссылки приходят от сборщиков, на сайт пускаем только http(s). */
    private static function httpUrlOrNull(?string $url): ?string
    {
        return null !== $url && 1 === preg_match('#^https?://#i', $url) ? $url : null;
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
