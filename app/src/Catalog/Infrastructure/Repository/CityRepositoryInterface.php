<?php

namespace App\Catalog\Infrastructure\Repository;

use App\Catalog\Domain\City;
use App\Catalog\Domain\CityNotFound;

interface CityRepositoryInterface
{
    public function save(City $city): void;

    public function delete(City $city): void;

    /**
     * @return City
     */
    public function findAll(): array;

    /**
     * @throws CityNotFound
     */
    public function findById(int $id): City;

    /**
     * @throws CityNotFound
     */
    public function findBySlug(string $slug): City;

    public function existsBySlug(string $slug): bool;
}
