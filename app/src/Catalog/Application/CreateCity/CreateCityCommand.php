<?php

namespace App\Catalog\Application\CreateCity;

use App\Shared\Bus\Command\CommandInterface;

final readonly class CreateCityCommand implements CommandInterface
{
    public function __construct(
        public string $name,
        public ?string $slug = null,
        public bool $available = false,
    ) {
    }
}
