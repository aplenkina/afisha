<?php

namespace App\Catalog\Application\GetCategory;

use App\Shared\Bus\Query\QueryInterface;

class GetCategoryQuery implements QueryInterface
{
    public function __construct(
        public string $alias,
    ) {
    }
}
