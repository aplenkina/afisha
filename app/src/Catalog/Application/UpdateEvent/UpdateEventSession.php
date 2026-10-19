<?php

namespace App\Catalog\Application\UpdateEvent;

use App\Catalog\Domain\SessionStatus;
use DateTimeImmutable;

final readonly class UpdateEventSession
{
    public function __construct(
        public DateTimeImmutable $startsAt,
        public ?int $id = null,
        public ?DateTimeImmutable $endsAt = null,
        public ?int $venueId = null,
        public ?string $ticketUrl = null,
        public SessionStatus $status = SessionStatus::Scheduled,
    ) {
    }
}
