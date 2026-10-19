<?php

namespace App\Catalog\Application\DeleteVenue;

use App\Catalog\Domain\VenueInUse;
use App\Catalog\Infrastructure\Repository\EventRepositoryInterface;
use App\Catalog\Infrastructure\Repository\VenueRepositoryInterface;
use App\Shared\Application\FlusherInterface;
use App\Shared\Bus\Command\HandlerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class DeleteVenueHandler implements HandlerInterface
{
    public function __construct(
        private VenueRepositoryInterface $venueRepository,
        private EventRepositoryInterface $eventRepository,
        private FlusherInterface $flusher,
    ) {
    }

    public function __invoke(DeleteVenueCommand $command): void
    {
        $venue = $this->venueRepository->findById($command->id);

        if ($this->eventRepository->existsByVenue($venue->getId())) {
            throw new VenueInUse($venue->getId());
        }

        $this->venueRepository->delete($venue);
        $this->flusher->flush();
    }
}
