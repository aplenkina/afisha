<?php

declare(strict_types=1);

namespace App\Admin\Application\ListEvents;

interface EventListRepository
{
    /** @return list<EventListItem> */
    public function find(ListEventsQuery $query, int $limit, int $offset): array;

    /** @return array<int, string> */
    public function categoryNames(): array;
}
