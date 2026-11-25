<?php

declare(strict_types=1);

namespace App\Telegram\Infrastructure\Http;

use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class TelegramUpdate
{
    public function __construct(
        #[Assert\Valid]
        public ?TelegramMessage $message = null,
        #[Assert\Valid]
        #[SerializedName('callback_query')]
        public ?TelegramCallback $callbackQuery = null,
    ) {
    }
}
