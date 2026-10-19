<?php

namespace App\Catalog\Infrastructure\Repository;

use App\Catalog\Application\ShowCategoryEvents\CategoryEventView;
use App\Catalog\Domain\Event;
use App\Catalog\Domain\EventNotFound;
use App\Catalog\Domain\EventStatus;

interface EventRepositoryInterface
{
    public function save(Event $event): void;

    public function delete(Event $event): void;

    /**
     * @return Event
     */
    public function findList(?EventStatus $status = null, int $limit = 200, ?int $createdBy = null, ?int $categoryId = null): array;

    public function existsByCity(int $cityId): bool;

    public function existsByVenue(int $venueId): bool;

    /**
     * @return list<CategoryEventView>
     */
    public function findUpcomingPublishedByCategory(int $categoryId, \DateTimeImmutable $now, int $limit = 200): array;

    public function existsBySource(int $sourceId): bool;

    /**
     * @throws EventNotFound
     */
    public function findById(int $id): Event;

    /**
     * @throws EventNotFound
     */
    public function findBySlug(string $slug): Event;

    public function existsBySlug(string $slug): bool;

    public function existsBySourceExternalId(int $sourceId, string $externalId): bool;
}
