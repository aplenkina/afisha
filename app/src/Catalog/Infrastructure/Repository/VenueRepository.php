<?php

namespace App\Catalog\Infrastructure\Repository;

use App\Catalog\Domain\Venue;
use App\Catalog\Domain\VenueNotFound;
use App\Shared\Infrastructure\Doctrine\AbstractDoctrineRepository;
use Override;

readonly class VenueRepository extends AbstractDoctrineRepository implements VenueRepositoryInterface
{    #[Override]
public function save(Venue $venue): void
{
    $this->add($venue);
}

    #[Override]
    public function delete(Venue $venue): void
    {
        $this->remove($venue);
    }

    #[Override]
    public function findAll(?int $cityId = null): array
    {
        return $this->repository(Venue::class)->findBy(
            $cityId !== null ? ['cityId' => $cityId] : [],
            ['name' => 'ASC'],
        );
    }

    #[Override]
    public function existsByCity(int $cityId): bool
    {
        return $this->repository(Venue::class)->count(['cityId' => $cityId]) > 0;
    }

    #[Override]
    public function findById(int $id): Venue
    {
        return $this->repository(Venue::class)->find($id) ?? throw new VenueNotFound($id);
    }

    #[Override]
    public function findByCityAndName(int $cityId, string $name): ?Venue
    {
        return $this->repository(Venue::class)->findOneBy(['cityId' => $cityId, 'name' => $name]);
    }

}
