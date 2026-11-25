<?php

declare(strict_types=1);

namespace App\Telegram\Application;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;

final readonly class ConversationHandler
{
    public function __construct(private TelegramCatalog $catalog, private SubscriberStore $subscribers)
    {
    }

    /** @return array<int, string> */
    #[AsMessageHandler]
    public function listCities(ListCities $query): array
    {
        return $this->catalog->availableCityNames();
    }

    #[AsMessageHandler]
    public function chooseCity(ChooseCity $command): ?string
    {
        $name = $this->catalog->availableCityName($command->cityId);
        if (null !== $name) {
            $this->subscribers->selectCity($command->chatId, $command->cityId);
        }

        return $name;
    }
}
