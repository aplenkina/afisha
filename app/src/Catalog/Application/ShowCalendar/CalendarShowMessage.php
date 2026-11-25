<?php

declare(strict_types=1);

namespace App\Catalog\Application\ShowCalendar;

use App\Shared\Bus\Query\QueryInterface;

/** @implements QueryInterface<CalendarShowResponse> */
final readonly class CalendarShowMessage implements QueryInterface
{
    public function __construct(
        public string $period,
        public string $citySlug,
        public bool $onlyFree,
        public string $categoryAlias = '',
        public string $dateFrom = '',
        public string $dateTo = '',
        public string $search = '',
        public int $page = 1,
    ) {
    }
}
