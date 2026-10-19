<?php

namespace App\Catalog\Application\DeleteEvent;

use App\Catalog\Infrastructure\Repository\EventRepositoryInterface;
use App\Shared\Application\FlusherInterface;
use App\Shared\Bus\Command\HandlerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class DeleteEventHandler implements HandlerInterface
{
    public function __construct(
        private EventRepositoryInterface $eventRepository,
        private FlusherInterface $flusher,
    ) {
    }

    public function __invoke(DeleteEventCommand $command): void
    {
        $this->eventRepository->delete($this->eventRepository->findById($command->id));
        $this->flusher->flush();
    }
}
