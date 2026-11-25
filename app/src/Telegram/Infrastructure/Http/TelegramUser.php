<?php

declare(strict_types=1);

namespace App\Telegram\Infrastructure\Http;

final readonly class TelegramUser
{
    public function __construct(public int $id)
    {
    }
}
