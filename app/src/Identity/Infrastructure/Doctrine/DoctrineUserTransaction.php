<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Doctrine;

use App\Identity\Application\UserTransaction;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(UserTransaction::class)]
final readonly class DoctrineUserTransaction implements UserTransaction
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function run(\Closure $operation): mixed
    {
        return $this->entityManager->wrapInTransaction(static function (EntityManagerInterface $manager) use ($operation): mixed {
            // User administration is infrequent. Serialize writes before checking the last administrator.
            $manager->getConnection()->executeStatement('LOCK TABLE app_user IN SHARE ROW EXCLUSIVE MODE');

            return $operation();
        });
    }
}
