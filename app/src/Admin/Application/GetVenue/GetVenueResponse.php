<?php

namespace App\Admin\Application\GetVenue;

use App\Catalog\Domain\Venue;
use App\Shared\Bus\Query\ResponseInterface;

final readonly class GetVenueResponse implements ResponseInterface
{
    public function __construct(
        public Venue $venue,
    ) {
    }
}
