<?php

declare(strict_types=1);

namespace App\Telegram\Application;

interface SubscriberStore
{
    public function selectCity(int $chatId, int $cityId): void;
}
