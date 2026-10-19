<?php

namespace App\Catalog\Application\ShowCategoryEvents;

use App\Shared\Bus\Query\ResponseInterface;

final readonly class ShowCategoryEventsResponse implements ResponseInterface
{
    /**
     * @param list<CategoryEventView> $events
     */
    public function __construct(
        public array $events,
    ) {
    }
}
