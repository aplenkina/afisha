<?php

declare(strict_types=1);

namespace App\Telegram\Domain;

use App\Catalog\Domain\City;

final class Subscriber
{
    private int $id;
    private int $chatId;
    private bool $active;
    private \DateTimeImmutable $createdAt;
    private City $city;
}
