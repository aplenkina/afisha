<?php

namespace App\Catalog\Application\CreateVenue;

use App\Catalog\Domain\Venue;
use App\Catalog\Infrastructure\Repository\CityRepositoryInterface;
use App\Catalog\Infrastructure\Repository\VenueRepositoryInterface;
use App\Shared\Application\FlusherInterface;
use App\Shared\Bus\Command\HandlerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class CreateVenueHandler implements HandlerInterface
{
    public function __construct(
        private VenueRepositoryInterface $venueRepository,
        private CityRepositoryInterface $cityRepository,
        private FlusherInterface $flusher,
    ) {
    }

    public function __invoke(CreateVenueCommand $command): int
    {
        $this->cityRepository->findById($command->cityId);

        $existing = $this->venueRepository->findByCityAndName($command->cityId, $command->name);

        if ($existing !== null) {
            $existing->update(
                cityId: $command->cityId,
                name: $command->name,
                address: $command->address,
                latitude: $command->latitude ?? $existing->getLatitude(),
                longitude: $command->longitude ?? $existing->getLongitude(),
            );
            $this->flusher->flush();

            return $existing->getId();
        }

        $venue = Venue::create(
            cityId: $command->cityId,
            name: $command->name,
            address: $command->address,
            latitude: $command->latitude,
            longitude: $command->longitude,
        );

        $this->venueRepository->save($venue);
        $this->flusher->flush();

        return $venue->getId();
    }
}
