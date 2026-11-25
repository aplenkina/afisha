<?php

declare(strict_types=1);

namespace App\Identity\Domain;

use App\Shared\Domain\DomainEntity;

class User extends DomainEntity
{
    private ?int $id = null;

    public function __construct(
        private string $email,
        private string $password,
        private UserRole $role,
        private bool $active,
        private \DateTimeImmutable $createdAt,
    ) {
    }

    public static function create(
        string $email,
        string $password,
        UserRole $role,
    ): self {
        return new self(
            email: UserCredentials::requireEmail($email),
            password: $password,
            role: $role,
            active: true,
            createdAt: new \DateTimeImmutable(),
        );
    }

    public function update(string $email, UserRole $role, bool $active): void
    {
        $this->email = UserCredentials::requireEmail($email);
        $this->role = $role;
        $this->active = $active;
    }

    public function changePassword(string $password): void
    {
        $this->password = $password;
    }

    public function isActiveAdmin(): bool
    {
        return $this->active && UserRole::Admin === $this->role;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getRole(): UserRole
    {
        return $this->role;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function getPasswordHash(): string
    {
        return $this->password;
    }
}
