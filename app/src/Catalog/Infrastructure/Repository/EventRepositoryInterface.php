<?php

namespace App\Catalog\Infrastructure\Repository;

use App\Catalog\Application\EventEditing\EventEditingRepository;
use App\Catalog\Application\ShowCategoryEvents\CategoryEventView;
use App\Catalog\Domain\Event;
use App\Catalog\Domain\EventNotFound;

interface EventRepositoryInterface extends EventEditingRepository
{
    public function delete(Event $event): void;

    public function existsByCity(int $cityId): bool;

    /** @return list<int> */
    public function findPublishedDuplicateSeedIds(): array;

    /** @return list<Event> */
    public function findDuplicatesOf(Event $event): array;

    public function existsByVenue(int $venueId): bool;

    /**
     * Опубликованные события категории с ближайшим не прошедшим сеансом, по возрастанию даты.
     *
     * @return list<CategoryEventView>
     */
    public function findUpcomingPublishedByCategory(int $categoryId, \DateTimeImmutable $now, int $limit = 200): array;

    /** @return list<CategoryEventView> */
    public function findCalendarEvents(\DateTimeImmutable $from, ?\DateTimeImmutable $until, ?int $cityId, bool $onlyFree, ?int $categoryId = null, string $search = '', int $limit = 200, int $offset = 0): array;

    /** @return list<CategoryEventView> */
    public function findDigestSessions(\DateTimeImmutable $from, \DateTimeImmutable $until, int $cityId, bool $onlyFree, ?int $categoryId = null): array;

    public function existsBySource(int $sourceId): bool;

    /**
     * @throws EventNotFound
     */
    public function findBySlug(string $slug): Event;
}
