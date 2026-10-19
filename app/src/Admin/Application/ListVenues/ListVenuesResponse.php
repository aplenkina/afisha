<?php

namespace App\Admin\Application\ListVenues;

use App\Catalog\Domain\Source;
use App\Catalog\Domain\Venue;
use App\Shared\Bus\Query\ResponseInterface;

final readonly class ListVenuesResponse implements ResponseInterface
{
    /**
     * @param list<Venue> $venues
     */
    public function __construct(
        public array $venues,
    ) {
    }
}
