<?php

namespace App\Catalog\Application\GetCategory;

use App\Catalog\Domain\Category;
use App\Shared\Bus\Query\ResponseInterface;

class GetCategoryResponse implements ResponseInterface
{
    public function __construct(
        public Category $category,
    ) {
    }
}
