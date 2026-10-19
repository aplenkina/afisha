<?php

namespace App\Admin\Application\ListCities;

use App\Catalog\Domain\City;
use App\Shared\Bus\Query\ResponseInterface;

final readonly class ListCitiesResponse implements ResponseInterface
{
    /**
     * @param list<City> $cities
     */
    public function __construct(
        public array $cities,
    ) {
    }
}
