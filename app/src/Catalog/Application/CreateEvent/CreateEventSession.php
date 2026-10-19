<?php

namespace App\Catalog\Application\CreateEvent;

use DateTimeImmutable;

final readonly class CreateEventSession
{
    public function __construct(
        public DateTimeImmutable $startsAt,
        public ?DateTimeImmutable $endsAt = null,
        public ?int $venueId = null,
        public ?string $ticketUrl = null,
    ) {
    }
}
