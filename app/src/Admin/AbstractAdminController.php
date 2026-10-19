<?php

namespace App\Admin;

use App\Shared\Domain\DomainError;
use DateTimeImmutable;
use DateTimeZone;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Throwable;

abstract class AbstractAdminController extends AbstractController
{
    private const string TIMEZONE = 'Europe/Minsk';

    public function __construct(
        protected MessageBusInterface $messageBus,
    ) {
    }

    protected function dispatch(object $message): mixed
    {
        try {
            return $this->messageBus->dispatch($message)->last(HandledStamp::class)?->getResult();
        } catch (HandlerFailedException $exception) {
            throw $exception->getPrevious() ?? $exception;
        }
    }

    protected function fail(Throwable $error, string $route, array $parameters = []): RedirectResponse
    {
        $message = $error instanceof DomainError ? $error->getMessage() : 'Не удалось сохранить: ' . $error->getMessage();
        $this->addFlash('danger', $message);

        return $this->redirectToRoute($route, $parameters);
    }

    protected function toInt(?string $value): ?int
    {
        return $value === null || trim($value) === '' ? null : (int) $value;
    }

    protected function toFloat(?string $value): ?float
    {
        return $value === null || trim($value) === '' ? null : (float) str_replace(',', '.', $value);
    }

    protected function toDecimal(?string $value): ?string
    {
        return $value === null || trim($value) === '' ? null : number_format((float) str_replace(',', '.', $value), 2, '.', '');
    }

    protected function toString(?string $value): ?string
    {
        return $value === null || trim($value) === '' ? null : trim($value);
    }

    protected function toDateTime(?string $value): ?DateTimeImmutable
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return new DateTimeImmutable($value, new DateTimeZone(self::TIMEZONE));
    }
}
