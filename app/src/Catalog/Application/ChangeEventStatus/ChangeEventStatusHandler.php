<?php

namespace App\Catalog\Application\ChangeEventStatus;

use App\Catalog\Domain\EventStatus;
use App\Catalog\Infrastructure\Repository\EventRepositoryInterface;
use App\Shared\Application\FlusherInterface;
use App\Shared\Bus\Command\HandlerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ChangeEventStatusHandler implements HandlerInterface
{
    public function __construct(
        private EventRepositoryInterface $eventRepository,
        private FlusherInterface $flusher,
    ) {
    }

    public function __invoke(ChangeEventStatusCommand $command): void
    {
        $event = $this->eventRepository->findById($command->id);

        match ($command->status) {
            EventStatus::Draft => $event->moveToDraft(),
            EventStatus::Published => $event->publish(),
            EventStatus::Rejected => $event->reject(),
            EventStatus::Archived => $event->archive(),
        };

        $this->flusher->flush();
    }
}
