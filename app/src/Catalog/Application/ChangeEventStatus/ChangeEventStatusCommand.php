<?php

namespace App\Catalog\Application\ChangeEventStatus;

use App\Catalog\Domain\EventStatus;
use App\Shared\Bus\Command\CommandInterface;

final readonly class ChangeEventStatusCommand implements CommandInterface
{
    public function __construct(
        public int $id,
        public EventStatus $status,
    ) {
    }
}
