<?php

namespace App\Catalog\Application\ChangeCategoryAvailability;

use App\Shared\Bus\Command\CommandInterface;

final readonly class ChangeCategoryAvailabilityCommand implements CommandInterface
{
    public function __construct(
        public int $id,
        public bool $available,
    ) {
    }
}
