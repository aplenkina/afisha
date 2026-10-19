<?php

namespace App\Catalog\Infrastructure\Repository;

use App\Catalog\Domain\Source;
use App\Catalog\Domain\SourceNotFound;

interface SourceRepositoryInterface
{
    public function save(Source $venue): void;

    public function delete(Source $venue): void;

    public function findAll(?int $cityId = null): array;

    public function existsByCity(int $cityId): bool;

    /**
     * @throws SourceNotFound
     */
    public function findById(int $id): Source;

    public function findByCityAndName(int $cityId, string $name): ?Source;

    public function findBySlug(string $slug): ?Source;
}
