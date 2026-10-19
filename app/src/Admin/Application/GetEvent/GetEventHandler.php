<?php

namespace App\Admin\Application\GetEvent;

use App\Catalog\Infrastructure\Repository\EventRepositoryInterface;
use App\Shared\Bus\Command\HandlerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetEventHandler implements HandlerInterface
{
    public function __construct(
        private EventRepositoryInterface $eventRepository,
    ) {
    }

    public function __invoke(GetEventQuery $query): GetEventResponse
    {
        return new GetEventResponse($this->eventRepository->findById($query->id));
    }
}
