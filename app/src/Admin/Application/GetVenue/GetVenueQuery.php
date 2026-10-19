<?php

namespace App\Admin\Application\GetVenue;

use App\Shared\Bus\Query\QueryInterface;

final readonly class GetVenueQuery implements QueryInterface
{
    public function __construct(
        public int $id,
    ) {
    }
}
