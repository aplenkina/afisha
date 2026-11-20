<?php

namespace App\Catalog\Infrastructure\Repository;

use App\Catalog\Domain\Category;
use App\Catalog\Domain\CategoryNotFound;
use App\Shared\Infrastructure\Doctrine\AbstractDoctrineRepository;

readonly class CategoryRepository extends AbstractDoctrineRepository implements CategoryRepositoryInterface
{
    public function save(Category $category): void
    {
        $this->add($category);
    }

    public function delete(Category $category): void
    {
        $this->remove($category);
    }

    /**
     * @return Category
     */
    public function findAll(bool $onlyAvailable = false): iterable
    {
        $queryBuilder = $this->repository(Category::class)->createQueryBuilder('c');

        if ($onlyAvailable) {
            $queryBuilder
                ->leftJoin('c.parent', 'parent')
                ->where($queryBuilder->expr()->eq('c.available', ':available'))
                ->andWhere('c.parent IS NULL OR parent.available = :available')
                ->setParameter('available', true);
        }

        return $queryBuilder
            ->addOrderBy('c.position', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findById(int $id): Category
    {
        return $this->repository(Category::class)->find($id) ?? throw new CategoryNotFound($id);
    }

    public function findByAlias(string $alias, bool $onlyAvailable = false): Category
    {
        $criteria = $onlyAvailable ? ['alias' => $alias, 'available' => true] : ['alias' => $alias];

        $category = $this->repository(Category::class)->findOneBy($criteria);
        if (null === $category || ($onlyAvailable && false === $category->getParent()?->isAvailable())) {
            throw new CategoryNotFound($alias);
        }

        return $category;
    }

    public function count(): int
    {
        $queryBuilder = $this->repository(Category::class)->createQueryBuilder('c');

        return (int) $queryBuilder
            ->select('COUNT(c.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }
}
