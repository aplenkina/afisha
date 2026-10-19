<?php

namespace App\Admin\Application\ListEvents;

use App\Catalog\Domain\Event;
use App\Shared\Bus\Query\ResponseInterface;

final readonly class ListEventsResponse implements ResponseInterface
{
    /**
     * @param list<Event> $events
     */
    public function __construct(
        public array $events,
    ) {
    }
}
