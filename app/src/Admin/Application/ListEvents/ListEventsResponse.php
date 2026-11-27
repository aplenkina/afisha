<?php

declare(strict_types=1);

namespace App\Admin\Application\ListEvents;

use App\Shared\Bus\Query\ResponseInterface;

final readonly class ListEventsResponse implements ResponseInterface
{
    /**
     * @param list<EventListItem> $events
     * @param array<int, string>  $categories
     */
    public function __construct(
        public array $events,
        public array $categories,
        public int $page,
        public bool $hasNext,
    ) {
    }
}
