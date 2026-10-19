<?php

namespace App\Catalog\Application\DeleteEvent;

use App\Shared\Bus\Command\CommandInterface;

final readonly class DeleteEventCommand implements CommandInterface
{
    public function __construct(
        public int $id,
    ) {
    }
}
