<?php

declare(strict_types=1);

namespace App\Telegram\Infrastructure\Http;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class TelegramMessage
{
    public function __construct(
        #[Assert\Valid]
        public TelegramChat $chat,
        #[Assert\Length(max: 4096)]
        public ?string $text = null,
    ) {
    }
}
