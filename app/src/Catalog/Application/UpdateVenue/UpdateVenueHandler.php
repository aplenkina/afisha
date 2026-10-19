<?php

namespace App\Catalog\Application\UpdateVenue;

use App\Catalog\Infrastructure\Repository\CityRepositoryInterface;
use App\Catalog\Infrastructure\Repository\VenueRepositoryInterface;
use App\Shared\Application\FlusherInterface;
use App\Shared\Bus\Command\HandlerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class UpdateVenueHandler implements HandlerInterface
{
    public function __construct(
        private VenueRepositoryInterface $venueRepository,
        private CityRepositoryInterface $cityRepository,
        private FlusherInterface $flusher,
    ) {
    }

    public function __invoke(UpdateVenueCommand $command): void
    {
        $venue = $this->venueRepository->findById($command->id);
        $this->cityRepository->findById($command->cityId);

        $venue->update(
            cityId: $command->cityId,
            name: $command->name,
            address: $command->address,
            latitude: $command->latitude,
            longitude: $command->longitude,
        );

        $this->flusher->flush();
    }
}
