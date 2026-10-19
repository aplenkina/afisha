<?php

namespace App\Admin\Application\ListEvents;

use App\Catalog\Infrastructure\Repository\EventRepositoryInterface;
use App\Shared\Bus\Command\HandlerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ListEventsHandler implements HandlerInterface
{
    public function __construct(
        private EventRepositoryInterface $eventRepository,
    ) {
    }

    public function __invoke(ListEventsQuery $query): ListEventsResponse
    {
        return new ListEventsResponse($this->eventRepository->findList($query->status, createdBy: $query->createdBy, categoryId: $query->categoryId));
    }
}
