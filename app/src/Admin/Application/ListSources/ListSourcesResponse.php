<?php

namespace App\Admin\Application\ListSources;

use App\Catalog\Domain\Source;
use App\Catalog\Domain\Venue;
use App\Shared\Bus\Query\ResponseInterface;

final readonly class ListSourcesResponse implements ResponseInterface
{
    /**
     * @param list<Source> $sources
     */
    public function __construct(
        public array $sources,
    ) {
    }
}
