<?php

namespace App\Catalog\Application\ShowCatalog;

use App\Shared\Bus\Query\QueryInterface;

class CatalogShowQuery implements QueryInterface
{
    public function __construct(
        public bool $onlyAvailable = false,
    ) {
    }
}
