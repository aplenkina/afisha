<?php

namespace App\Shared\Infrastructure\Doctrine;

use App\Shared\Domain\DomainEntity;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;

abstract readonly class AbstractDoctrineRepository
{
    public function __construct(
        protected EntityManagerInterface $entityManager,
    ) {
    }

    public function repository(string $entityClass): EntityRepository
    {
        return $this->entityManager->getRepository($entityClass);
    }

    protected function add(DomainEntity $entity): void
    {
        $this->entityManager->persist($entity);
    }

    protected function remove(DomainEntity $entity): void
    {
        $this->entityManager->remove($entity);
    }
}
