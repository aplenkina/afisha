<?php

declare(strict_types=1);

namespace App\Telegram\Application;

final readonly class ChooseCity
{
    public function __construct(public int $chatId, public int $cityId)
    {
    }
}
