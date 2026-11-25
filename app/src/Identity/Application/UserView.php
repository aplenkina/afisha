<?php

declare(strict_types=1);

namespace App\Identity\Application;

use App\Identity\Domain\User;
use App\Identity\Domain\UserRole;

final readonly class UserView
{
    public function __construct(
        public int $id,
        public string $email,
        public UserRole $role,
        public bool $active,
    ) {
    }

    public static function fromUser(User $user): self
    {
        return new self($user->getId(), $user->getEmail(), $user->getRole(), $user->isActive());
    }
}
