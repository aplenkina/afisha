<?php

namespace App\Admin\Application\ListSources;

use App\Shared\Bus\Query\QueryInterface;

final readonly class ListSourcesQuery implements QueryInterface
{
    public function __construct(
        public ?int $cityId = null,
    ) {
    }
}
