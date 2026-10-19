<?php

namespace App\Catalog\Application\ShowCategoryEvents;

use App\Catalog\Infrastructure\Repository\EventRepositoryInterface;
use App\Shared\Bus\Command\HandlerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ShowCategoryEventsHandler implements HandlerInterface
{
    public function __construct(
        private EventRepositoryInterface $eventRepository,
    ) {
    }

    public function __invoke(ShowCategoryEventsQuery $query): ShowCategoryEventsResponse
    {
        return new ShowCategoryEventsResponse(
            $this->eventRepository->findUpcomingPublishedByCategory($query->categoryId, new \DateTimeImmutable(), $query->limit),
        );
    }
}
