<?php

namespace App\Catalog\Application\DeleteCity;

use App\Shared\Bus\Command\CommandInterface;

final readonly class DeleteCityCommand implements CommandInterface
{
    public function __construct(
        public int $id,
    ) {
    }
}
