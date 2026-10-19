<?php

namespace App\Admin\Application\GetCity;

use App\Shared\Bus\Query\QueryInterface;

final readonly class GetCityQuery implements QueryInterface
{
    public function __construct(
        public int $id,
    ) {
    }
}
