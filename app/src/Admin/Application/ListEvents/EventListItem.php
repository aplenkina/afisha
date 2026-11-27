<?php

declare(strict_types=1);

namespace App\Admin\Application\ListEvents;

use App\Catalog\Domain\EventStatus;

final readonly class EventListItem
{
    public function __construct(
        public int $id,
        public string $title,
        public string $slug,
        public EventStatus $status,
        public ?string $city,
        public ?string $category,
        public int $sessionCount,
        public ?\DateTimeImmutable $firstSession,
    ) {
    }
}
