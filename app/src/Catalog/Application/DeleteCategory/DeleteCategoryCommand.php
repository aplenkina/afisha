<?php

namespace App\Catalog\Application\DeleteCategory;

use App\Shared\Bus\Command\CommandInterface;

class DeleteCategoryCommand implements CommandInterface
{
    public function __construct(
        public string $id,
    ) {
    }
}
