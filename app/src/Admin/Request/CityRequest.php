<?php

namespace App\Admin\Request;

use Symfony\Component\Validator\Constraints as Assert;

final class CityRequest
{
    public function __construct(
        public ?string $id = null,
        #[Assert\NotBlank]
        #[Assert\Length(max: 64)]
        public string $name = '',
        #[Assert\Length(max: 64)]
        public ?string $slug = null,
        public ?string $available = null,
    ) {
    }
}
