<?php

namespace App\Catalog\Application\CreateVenue;

use App\Shared\Bus\Command\CommandInterface;

final readonly class CreateVenueCommand implements CommandInterface
{
    public function __construct(
        public int $cityId,
        public string $name,
        public string $address,
        public ?float $latitude = null,
        public ?float $longitude = null,
    ) {
    }
}
