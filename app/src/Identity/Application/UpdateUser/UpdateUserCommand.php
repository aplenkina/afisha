<?php

namespace App\Identity\Application\UpdateUser;

use App\Identity\Domain\UserRole;
use App\Shared\Bus\Command\CommandInterface;

final readonly class UpdateUserCommand implements CommandInterface
{
    public function __construct(
        public int $id,
        public string $email,
        public UserRole $role,
        public bool $active,
        public ?string $plainPassword = null,
    ) {
    }
}
