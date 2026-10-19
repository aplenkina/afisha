<?php

namespace App\Catalog\Application\ShowCatalog;

use App\Catalog\Domain\Category;
use App\Shared\Bus\Query\ResponseInterface;

class CatalogShowResponse implements ResponseInterface
{
    /**
     * @param list<Category> $categories
     */
    public function __construct(
        public array $categories,
    ) {
    }
}
