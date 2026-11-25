<?php

declare(strict_types=1);

namespace App\Telegram\Infrastructure\Http;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class TelegramCallback
{
    public function __construct(
        #[Assert\Length(min: 1, max: 256)]
        public string $id,
        public TelegramUser $from,
        #[Assert\Valid]
        public ?TelegramMessage $message = null,
        #[Assert\Length(max: 64, countUnit: Assert\Length::COUNT_BYTES)]
        public ?string $data = null,
    ) {
    }
}
