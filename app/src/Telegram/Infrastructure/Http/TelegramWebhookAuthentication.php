<?php

declare(strict_types=1);

namespace App\Telegram\Infrastructure\Http;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;

#[AsEventListener]
final readonly class TelegramWebhookAuthentication
{
    public function __construct(
        #[Autowire('%env(default::TELEGRAM_WEBHOOK_SECRET)%')]
        private ?string $secret,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if (!$event->isMainRequest() || TelegramWebhookController::ROUTE !== $request->attributes->get('_route')) {
            return;
        }

        if (null === $this->secret || '' === $this->secret || !hash_equals($this->secret, $request->headers->get('X-Telegram-Bot-Api-Secret-Token', ''))) {
            $event->setResponse(new Response(status: Response::HTTP_FORBIDDEN));
        }
    }
}
