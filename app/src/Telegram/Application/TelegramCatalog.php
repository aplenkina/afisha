<?php

declare(strict_types=1);

namespace App\Telegram\Application;

interface TelegramCatalog
{
    /** @return array<int, string> */
    public function availableCityNames(): array;

    public function availableCityName(int $id): ?string;

    public function digest(PreviewDigest $query, \DateTimeImmutable $from, \DateTimeImmutable $until): Digest;
}
