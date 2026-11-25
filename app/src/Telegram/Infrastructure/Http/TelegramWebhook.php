<?php

declare(strict_types=1);

namespace App\Telegram\Infrastructure\Http;

use App\Telegram\Application\ChooseCity;
use App\Telegram\Application\ListCities;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;

final class TelegramWebhook
{
    use HandleTrait;

    public function __construct(MessageBusInterface $messageBus, private readonly TelegramReplyPresenter $presenter)
    {
        $this->messageBus = $messageBus;
    }

    public function respond(TelegramUpdate $update): Response
    {
        $message = $update->message;
        if ('private' === $message?->chat->type && null !== $message->text && preg_match('/^\/start(?:\s|$)/u', $message->text)) {
            return $this->presenter->showCities($message->chat->id, $this->handle(new ListCities()));
        }

        $callback = $update->callbackQuery;
        $chat = $callback?->message?->chat;
        if ('private' === $chat?->type && $callback->from->id === $chat->id
            && null !== $callback->data && preg_match('/^city:([1-9][0-9]{0,9})$/D', $callback->data, $match)
        ) {
            return $this->presenter->citySelection($callback->id, $this->handle(new ChooseCity($chat->id, (int) $match[1])));
        }

        return new Response();
    }
}
