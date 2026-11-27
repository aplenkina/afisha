<?php

declare(strict_types=1);

namespace App\Admin\Application\ListEvents;

use App\Catalog\Domain\EventStatus;
use App\Shared\Bus\Query\QueryInterface;

/** @implements QueryInterface<ListEventsResponse> */
final readonly class ListEventsQuery implements QueryInterface
{
    public function __construct(
        public ?EventStatus $status = null,
        public ?int $createdBy = null,
        public ?int $categoryId = null,
        public bool $onlyUnpublished = false,
        public string $search = '',
        public bool $regex = false,
        public int $page = 1,
    ) {
    }

    public function usesRegex(): bool
    {
        return $this->regex && '' !== $this->search;
    }
}
