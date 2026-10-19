<?php

namespace App\Admin\Application\ListEvents;

use App\Catalog\Domain\EventStatus;
use App\Shared\Bus\Query\QueryInterface;

final readonly class ListEventsQuery implements QueryInterface
{
    public function __construct(
        public ?EventStatus $status = null,
        public ?int $createdBy = null,
        public ?int $categoryId = null,
    ) {
    }
}
