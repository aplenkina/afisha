<?php

namespace App\Catalog\Application\DeleteVenue;

use App\Shared\Bus\Command\CommandInterface;

final readonly class DeleteVenueCommand implements CommandInterface
{
    public function __construct(
        public int $id,
    ) {
    }
}
