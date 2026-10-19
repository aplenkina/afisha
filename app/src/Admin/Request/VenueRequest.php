<?php

namespace App\Admin\Request;

use Symfony\Component\Validator\Constraints as Assert;

final class VenueRequest
{
    public function __construct(
        public ?string $id = null,
        #[Assert\NotBlank]
        public string $cityId = '',
        #[Assert\NotBlank]
        #[Assert\Length(max: 128)]
        public string $name = '',
        #[Assert\NotBlank]
        #[Assert\Length(max: 255)]
        public string $address = '',
        public ?string $latitude = null,
        public ?string $longitude = null,
    ) {
    }
}
