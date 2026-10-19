<?php

namespace App\Identity\Application\CreateUser;

use App\Identity\Domain\UserRole;
use App\Shared\Bus\Command\CommandInterface;

final readonly class CreateUserCommand implements CommandInterface
{
    public function __construct(
        public string $email,
        public string $plainPassword,
        public UserRole $role,
    ) {
    }
}
