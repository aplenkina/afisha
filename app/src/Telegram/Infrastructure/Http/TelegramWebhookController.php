<?php

namespace App\Telegram\Infrastructure\Http;

use App\Telegram\Application\StartConversation;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final readonly class TelegramWebhookController
{
    public function __construct(
        private StartConversation $conversation,
        #[Autowire('%env(default::TELEGRAM_WEBHOOK_SECRET)%')]
        private ?string $secret,
    ) {
    }

    #[Route('/telegram/webhook', name: 'telegram_webhook', methods: ['POST'])]
    public function __invoke(Request $request): Response
    {
        if (null === $this->secret || '' === $this->secret || !hash_equals($this->secret, $request->headers->get('X-Telegram-Bot-Api-Secret-Token', ''))) {
            return new Response(status: Response::HTTP_FORBIDDEN);
        }

        try {
            $update = $request->toArray();
        } catch (JsonException) {
            return new Response(status: Response::HTTP_BAD_REQUEST);
        }
        $message = $update['message'] ?? null;
        if (($message['chat']['type'] ?? null) === 'private'
            && is_int($message['chat']['id'] ?? null)
            && is_string($message['text'] ?? null)
            && preg_match('/^\/start(?:\s|$)/u', $message['text'])
        ) {
            return new JsonResponse(['method' => 'sendMessage', 'chat_id' => $message['chat']['id']] + $this->conversation->start());
        }

        $callback = $update['callback_query'] ?? null;
        $chat = $callback['message']['chat'] ?? null;
        if (($chat['type'] ?? null) === 'private'
            && is_int($chat['id'] ?? null)
            && ($callback['from']['id'] ?? null) === $chat['id']
            && is_string($callback['id'] ?? null)
            && is_string($callback['data'] ?? null)
            && preg_match('/^city:([1-9][0-9]{0,9})$/D', $callback['data'], $match)
        ) {
            $result = $this->conversation->selectCity($chat['id'], (int) $match[1]);

            return new JsonResponse(['method' => 'answerCallbackQuery', 'callback_query_id' => $callback['id']] + $result);
        }

        return new Response();
    }
}
