<?php

declare(strict_types=1);

namespace App\Catalog\Application\EventEditing;

interface EventReferences
{
    /** @param list<?int> $venueIds */
    public function check(int $categoryId, int $cityId, array $venueIds): void;
}
