<?php

namespace App\Catalog\Application\ChangeEventStatus;

use App\Catalog\Application\MergeDuplicateEvents\MergeDuplicateEvents;
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
        private MergeDuplicateEvents $duplicates,
    ) {
    }

    public function __invoke(ChangeEventStatusCommand $command): EventStatus
    {
        if (EventStatus::Published === $command->status) {
            return $this->duplicates->publish($command->id) ? EventStatus::Published : EventStatus::Duplicate;
        }

        $event = $this->eventRepository->findById($command->id);

        match ($command->status) {
            EventStatus::Draft => $event->moveToDraft(),
            EventStatus::Rejected => $event->reject(),
            EventStatus::Archived => $event->archive(),
            EventStatus::Duplicate => throw new \InvalidArgumentException('Выберите основную карточку в списке возможных дублей.'),
        };

        $this->flusher->flush();

        return $event->getStatus();
    }
}
