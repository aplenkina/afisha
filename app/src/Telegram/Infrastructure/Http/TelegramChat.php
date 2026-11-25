<?php

declare(strict_types=1);

namespace App\Telegram\Infrastructure\Http;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class TelegramChat
{
    public function __construct(
        public int $id,
        #[Assert\Length(min: 1, max: 32)]
        #[Assert\Regex('/^[a-z_]+$/D')]
        public string $type,
    ) {
    }
}
