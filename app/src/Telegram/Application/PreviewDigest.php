<?php

declare(strict_types=1);

namespace App\Telegram\Application;

final readonly class PreviewDigest
{
    public function __construct(
        public string $citySlug,
        public int $days,
        public bool $onlyFree,
        public ?string $categoryAlias,
    ) {
    }
}
