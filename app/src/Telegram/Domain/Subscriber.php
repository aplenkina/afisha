<?php

declare(strict_types=1);

namespace App\Telegram\Domain;

use App\Catalog\Domain\City;

final class Subscriber
{
    private ?int $id = null;
    private bool $active = true;
    private \DateTimeImmutable $createdAt;

    public function __construct(private int $chatId, private City $city)
    {
        $this->createdAt = new \DateTimeImmutable();
    }
}
