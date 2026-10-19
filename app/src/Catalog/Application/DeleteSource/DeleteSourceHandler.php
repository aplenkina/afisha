<?php

namespace App\Catalog\Application\DeleteSource;

use App\Catalog\Domain\SourceInUse;
use App\Catalog\Infrastructure\Repository\EventRepositoryInterface;
use App\Catalog\Infrastructure\Repository\SourceRepositoryInterface;
use App\Shared\Application\FlusherInterface;
use App\Shared\Bus\Command\HandlerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class DeleteSourceHandler implements HandlerInterface
{
    public function __construct(
        private SourceRepositoryInterface $sourceRepository,
        private EventRepositoryInterface $eventRepository,
        private FlusherInterface $flusher,
    ) {
    }

    public function __invoke(DeleteSourceCommand $command): void
    {
        $source = $this->sourceRepository->findById($command->id);

        if ($this->eventRepository->existsBySource($source->getId())) {
            throw new SourceInUse($source->getId());
        }

        $this->sourceRepository->delete($source);
        $this->flusher->flush();
    }
}
