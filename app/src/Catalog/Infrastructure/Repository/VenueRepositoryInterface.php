<?php

namespace App\Catalog\Infrastructure\Repository;

use App\Catalog\Domain\Venue;
use App\Catalog\Domain\VenueNotFound;

interface VenueRepositoryInterface
{
    public function save(Venue $venue): void;

    public function delete(Venue $venue): void;

    /**
     * @return Venue
     */
    public function findAll(?int $cityId = null): array;

    public function existsByCity(int $cityId): bool;

    /**
     * @throws VenueNotFound
     */
    public function findById(int $id): Venue;

    public function findByCityAndName(int $cityId, string $name): ?Venue;
}
