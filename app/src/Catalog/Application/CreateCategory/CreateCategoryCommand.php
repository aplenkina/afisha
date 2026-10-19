<?php

namespace App\Catalog\Application\CreateCategory;

use App\Shared\Bus\Command\CommandInterface;

final readonly class CreateCategoryCommand implements CommandInterface
{
    public function __construct(
        public ?int $id,
        public string $name,
        public string $alias,
    ) {
    }
}
