<?php

namespace App\Catalog\Application\ShowCategoryEvents;

use App\Shared\Bus\Query\QueryInterface;

final readonly class ShowCategoryEventsQuery implements QueryInterface
{
    public function __construct(
        public int $categoryId,
        public int $limit = 200,
    ) {
    }
}
