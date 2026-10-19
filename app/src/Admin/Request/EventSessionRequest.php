<?php

namespace App\Admin\Request;

use Symfony\Component\Validator\Constraints as Assert;

final class EventSessionRequest
{
    public function __construct(
        public ?string $id = null,
        public ?string $startsAt = null,
        public ?string $endsAt = null,
        public ?string $venueId = null,
        #[Assert\Url]
        #[Assert\Length(max: 512)]
        public ?string $ticketUrl = null,
        public ?string $status = null,
    ) {
    }
}
