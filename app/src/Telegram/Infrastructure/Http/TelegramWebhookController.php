<?php

declare(strict_types=1);

namespace App\Telegram\Infrastructure\Http;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

final readonly class TelegramWebhookController
{
    public const string ROUTE = 'telegram_webhook';

    public function __construct(private TelegramWebhook $webhook)
    {
    }

    #[Route('/telegram/webhook', name: self::ROUTE, methods: ['POST'])]
    public function __invoke(
        #[MapRequestPayload(acceptFormat: 'json', validationFailedStatusCode: Response::HTTP_BAD_REQUEST)]
        TelegramUpdate $update,
    ): Response {
        return $this->webhook->respond($update);
    }
}
