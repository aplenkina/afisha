<?php

namespace App\Catalog\Infrastructure\Repository;

use App\Catalog\Domain\Category;

interface CategoryRepositoryInterface
{
    /**
     * @return Category
     */
    public function findAll(bool $onlyAvailable = false): iterable;

    public function findById(int $id): Category;

    public function findByAlias(string $alias, bool $onlyAvailable = false): Category;

    public function count(): int;
}
