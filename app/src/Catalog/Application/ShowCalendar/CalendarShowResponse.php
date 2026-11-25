<?php

declare(strict_types=1);

namespace App\Catalog\Application\ShowCalendar;

use App\Catalog\Application\ShowCategoryEvents\CategoryEventView;
use App\Catalog\Domain\Category;
use App\Catalog\Domain\City;
use App\Shared\Bus\Query\ResponseInterface;

final readonly class CalendarShowResponse implements ResponseInterface
{
    /**
     * @param array<City>                           $cities
     * @param list<Category>                        $categories
     * @param array<string,list<CategoryEventView>> $days
     */
    public function __construct(
        public array $cities,
        public ?City $selectedCity,
        public array $categories,
        public ?Category $selectedCategory,
        public string $period,
        public bool $onlyFree,
        public array $days,
        public string $dateFrom,
        public string $dateTo,
        public ?string $dateError,
        public string $search,
        public int $page,
        public bool $hasNext,
    ) {
    }
}
