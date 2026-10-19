<?php

namespace App\Catalog\Application\ChangeSourceAvailability;

use App\Catalog\Infrastructure\Repository\SourceRepositoryInterface;
use App\Shared\Application\FlusherInterface;
use App\Shared\Bus\Command\HandlerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ChangeSourceAvailabilityHandler implements HandlerInterface
{
    public function __construct(
        private SourceRepositoryInterface $sourceRepository,
        private FlusherInterface $flusher,
    ) {
    }

    public function __invoke(ChangeSourceAvailabilityCommand $command): void
    {
        $source = $this->sourceRepository->findById($command->id);

        $command->available ? $source->enable() : $source->disable();

        $this->flusher->flush();
    }
}
