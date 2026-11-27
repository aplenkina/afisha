<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Repository;

use App\Catalog\Application\EventEditing\EventReferences;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(EventReferences::class)]
final readonly class DoctrineEventReferences implements EventReferences
{
    public function __construct(
        private CategoryRepositoryInterface $categories,
        private CityRepositoryInterface $cities,
        private VenueRepositoryInterface $venues,
    ) {
    }

    public function check(int $categoryId, int $cityId, array $venueIds): void
    {
        $this->categories->findById($categoryId);
        $this->cities->findById($cityId);
        foreach (array_unique(array_filter($venueIds)) as $venueId) {
            $this->venues->findById($venueId);
        }
    }
}
