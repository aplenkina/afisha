<?php

namespace App\Catalog\Application\ChangeSourceAvailability;

use App\Shared\Bus\Command\CommandInterface;

final readonly class ChangeSourceAvailabilityCommand implements CommandInterface
{
    public function __construct(
        public int $id,
        public bool $available,
    ) {
    }
}
