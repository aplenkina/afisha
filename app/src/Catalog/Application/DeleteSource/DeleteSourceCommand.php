<?php

namespace App\Catalog\Application\DeleteSource;

use App\Shared\Bus\Command\CommandInterface;

final readonly class DeleteSourceCommand implements CommandInterface
{
    public function __construct(
        public int $id,
    ) {
    }
}
