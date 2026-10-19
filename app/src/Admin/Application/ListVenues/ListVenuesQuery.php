<?php

namespace App\Admin\Application\ListVenues;

use App\Shared\Bus\Query\QueryInterface;

final readonly class ListVenuesQuery implements QueryInterface
{
    public function __construct(
        public ?int $cityId = null,
    ) {
    }
}
