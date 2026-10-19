<?php

namespace App\Catalog\Infrastructure\Repository;

use App\Catalog\Domain\City;
use App\Catalog\Domain\CityNotFound;
use App\Shared\Infrastructure\Doctrine\AbstractDoctrineRepository;

readonly class CityRepository extends AbstractDoctrineRepository implements CityRepositoryInterface
{
    #[\Override]
    public function save(City $city): void
    {
        $this->add($city);
    }

    #[\Override]
    public function delete(City $city): void
    {
        $this->remove($city);
    }

    #[\Override]
    public function findAll(): array
    {
        return $this->repository(City::class)->findBy([], ['name' => 'ASC']);
    }

    #[\Override]
    public function findById(int $id): City
    {
        return $this->repository(City::class)->find($id) ?? throw new CityNotFound($id);
    }

    #[\Override]
    public function findBySlug(string $slug): City
    {
        return $this->repository(City::class)->findOneBy(['slug' => $slug]) ?? throw new CityNotFound($slug);
    }

    #[\Override]
    public function existsBySlug(string $slug): bool
    {
        return $this->repository(City::class)->count(['slug' => $slug]) > 0;
    }
}
