<?php

declare(strict_types=1);

namespace App\Catalog\Application\EventEditing;

use App\Catalog\Domain\Event;
use App\Catalog\Domain\EventNotFound;

interface EventEditingRepository
{
    /** @throws EventNotFound */
    public function findById(int $id): Event;

    /** @return list<array{id: int, title: string, status: string, starts_at: string}> */
    public function findDuplicateCandidates(int $eventId, bool $publishedOnly = false): array;

    public function save(Event $event): void;

    public function existsBySlug(string $slug): bool;

    public function existsBySourceExternalId(int $sourceId, string $externalId): bool;
}
