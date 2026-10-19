<?php

namespace App\Catalog\Infrastructure\Repository;

use App\Catalog\Domain\Source;
use App\Catalog\Domain\SourceNotFound;
use App\Shared\Infrastructure\Doctrine\AbstractDoctrineRepository;

readonly class SourceRepository extends AbstractDoctrineRepository implements SourceRepositoryInterface
{
    #[\Override]
    public function save(Source $source): void
    {
        $this->add($source);
    }

    #[\Override]
    public function delete(Source $source): void
    {
        $this->remove($source);
    }

    #[\Override]
    public function findAll(?int $cityId = null): array
    {
        return $this->repository(Source::class)->findBy(
            null !== $cityId ? ['cityId' => $cityId] : [],
            ['name' => 'ASC'],
        );
    }

    #[\Override]
    public function existsByCity(int $cityId): bool
    {
        return $this->repository(Source::class)->count(['cityId' => $cityId]) > 0;
    }

    #[\Override]
    public function findById(int $id): Source
    {
        return $this->repository(Source::class)->find($id) ?? throw new SourceNotFound($id);
    }

    #[\Override]
    public function findByCityAndName(int $cityId, string $name): ?Source
    {
        return $this->repository(Source::class)->findOneBy(['cityId' => $cityId, 'name' => $name]);
    }

    #[\Override]
    public function findBySlug(string $slug): ?Source
    {
        return $this->repository(Source::class)->findOneBy(['slug' => $slug]);
    }
}
