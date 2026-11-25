<?php

declare(strict_types=1);

namespace App\Telegram\Infrastructure\Http;

use Symfony\Component\HttpFoundation\JsonResponse;

final class TelegramReplyPresenter
{
    /** @param array<int, string> $cities */
    public function showCities(int $chatId, array $cities): JsonResponse
    {
        $response = ['method' => 'sendMessage', 'chat_id' => $chatId];
        if ([] === $cities) {
            return new JsonResponse($response + ['text' => 'В афише пока нет доступных городов.']);
        }

        $buttons = [];
        foreach ($cities as $id => $name) {
            $buttons[] = [['text' => $name, 'callback_data' => 'city:'.$id]];
        }

        return new JsonResponse($response + [
            'text' => 'В каком городе искать события?',
            'reply_markup' => ['inline_keyboard' => $buttons],
        ]);
    }

    public function citySelection(string $callbackId, ?string $cityName): JsonResponse
    {
        return new JsonResponse([
            'method' => 'answerCallbackQuery',
            'callback_query_id' => $callbackId,
            'text' => null === $cityName
                ? 'Этот город недоступен. Отправь /start, чтобы выбрать другой.'
                : 'Город выбран: '.$cityName.'.',
        ]);
    }
}
