<?php

namespace App\Catalog\Application\UpdateVenue;

use App\Shared\Bus\Command\CommandInterface;

final readonly class UpdateVenueCommand implements CommandInterface
{
    public function __construct(
        public int $id,
        public int $cityId,
        public string $name,
        public string $address,
        public ?float $latitude = null,
        public ?float $longitude = null,
    ) {
    }
}
