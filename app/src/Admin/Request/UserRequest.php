<?php

namespace App\Admin\Request;

use Symfony\Component\Validator\Constraints as Assert;

final class UserRequest
{
    public function __construct(
        public ?string $id = null,
        #[Assert\NotBlank]
        #[Assert\Email]
        #[Assert\Length(max: 180)]
        public string $email = '',
        #[Assert\NotBlank]
        public string $role = '',
        public ?string $active = null,
        #[Assert\Length(min: 8)]
        public ?string $password = null,
    ) {
    }
}
