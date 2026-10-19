<?php

namespace App\Admin\Application\GetCity;

use App\Catalog\Domain\City;
use App\Shared\Bus\Query\ResponseInterface;

final readonly class GetCityResponse implements ResponseInterface
{
    public function __construct(
        public City $city,
    ) {
    }
}
