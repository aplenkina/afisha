<?php

namespace App\Catalog\Application\UpdateCity;

use App\Shared\Bus\Command\CommandInterface;

final readonly class UpdateCityCommand implements CommandInterface
{
    public function __construct(
        public int $id,
        public string $name,
        public string $slug,
        public bool $available,
    ) {
    }
}
