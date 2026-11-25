<?php

declare(strict_types=1);

namespace App\Telegram\Domain;

enum DigestHorizon: int
{
    case Week = 7;
    case TwoWeeks = 14;
    case ThirtyDays = 30;

    public static function fromDays(int $days): self
    {
        return self::tryFrom($days) ?? throw new \InvalidArgumentException('Горизонт должен быть 7, 14 или 30 дней.');
    }
}
