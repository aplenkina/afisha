<?php

declare(strict_types=1);

namespace App\Telegram\Application;

use App\Catalog\Application\ShowCategoryEvents\CategoryEventView;

final readonly class Digest
{
    /** @param list<CategoryEventView> $sessions */
    public function __construct(
        public string $citySlug,
        public string $cityName,
        public ?string $categoryAlias,
        public int $days,
        public bool $onlyFree,
        public \DateTimeImmutable $from,
        public \DateTimeImmutable $until,
        public array $sessions,
    ) {
    }
}
