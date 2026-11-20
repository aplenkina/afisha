<?php

namespace App\Telegram\Application;

use App\Catalog\Infrastructure\Repository\CityRepositoryInterface;

final readonly class StartConversation
{
    public function __construct(private CityRepositoryInterface $cities, private SubscriberStore $subscribers)
    {
    }

    public function start(): array
    {
        $buttons = [];
        foreach ($this->cities->findAll() as $city) {
            if ($city->isAvailable()) {
                $buttons[] = [['text' => $city->getName(), 'callback_data' => 'city:'.$city->getId()]];
            }
        }

        return [] === $buttons
            ? ['text' => 'В афише пока нет доступных городов.']
            : ['text' => 'В каком городе искать события?', 'reply_markup' => ['inline_keyboard' => $buttons]];
    }

    public function selectCity(int $chatId, int $cityId): array
    {
        foreach ($this->cities->findAll() as $city) {
            if ($city->getId() === $cityId && $city->isAvailable()) {
                $this->subscribers->selectCity($chatId, $cityId);

                return ['text' => 'Город выбран: '.$city->getName().'.'];
            }
        }

        return ['text' => 'Этот город недоступен. Отправь /start, чтобы выбрать другой.'];
    }
}
