<?php

namespace App\Catalog\Application\DeleteCity;

use App\Catalog\Domain\CityInUse;
use App\Catalog\Infrastructure\Repository\CityRepositoryInterface;
use App\Catalog\Infrastructure\Repository\EventRepositoryInterface;
use App\Catalog\Infrastructure\Repository\VenueRepositoryInterface;
use App\Shared\Application\FlusherInterface;
use App\Shared\Bus\Command\HandlerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class DeleteCityHandler implements HandlerInterface
{
    public function __construct(
        private CityRepositoryInterface $cityRepository,
        private VenueRepositoryInterface $venueRepository,
        private EventRepositoryInterface $eventRepository,
        private FlusherInterface $flusher,
    ) {
    }

    public function __invoke(DeleteCityCommand $command): void
    {
        $city = $this->cityRepository->findById($command->id);

        if ($this->venueRepository->existsByCity($city->getId()) || $this->eventRepository->existsByCity($city->getId())) {
            throw new CityInUse($city->getId());
        }

        $this->cityRepository->delete($city);
        $this->flusher->flush();
    }
}
