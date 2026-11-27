<?php

declare(strict_types=1);

namespace App\Admin\Infrastructure\Doctrine;

use App\Admin\Application\EventForm\EventFormOptions;
use App\Admin\Application\EventForm\EventFormOptionsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(EventFormOptionsRepository::class)]
final readonly class DoctrineEventFormOptions implements EventFormOptionsRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function options(): EventFormOptions
    {
        $connection = $this->entityManager->getConnection();

        return new EventFormOptions(
            $connection->fetchAllAssociative('SELECT id, name FROM city ORDER BY name, id'),
            $connection->fetchAllAssociative('SELECT id, name FROM venue ORDER BY name, id'),
            $connection->fetchAllAssociative('SELECT id, name FROM category ORDER BY position, id'),
        );
    }
}
