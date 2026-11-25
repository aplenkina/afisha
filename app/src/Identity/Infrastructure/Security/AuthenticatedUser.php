<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use App\Identity\Domain\User;
use App\Identity\Domain\UserRole;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

final readonly class AuthenticatedUser implements UserInterface, PasswordAuthenticatedUserInterface
{
    public function __construct(
        public int $id,
        private string $email,
        private UserRole $role,
        private string $password,
        public bool $active,
    ) {
    }

    public static function fromUser(User $user): self
    {
        return new self($user->getId(), $user->getEmail(), $user->getRole(), $user->getPasswordHash(), $user->isActive());
    }

    #[\Override]
    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    #[\Override]
    public function getRoles(): array
    {
        return [$this->role->value];
    }

    #[\Override]
    public function getPassword(): string
    {
        return $this->password;
    }

    public function __serialize(): array
    {
        return [$this->id, $this->email, $this->role, hash('crc32c', $this->password), $this->active];
    }

    public function __unserialize(array $data): void
    {
        [$this->id, $this->email, $this->role, $this->password, $this->active] = $data;
    }
}
