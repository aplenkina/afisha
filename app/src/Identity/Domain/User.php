<?php

declare(strict_types=1);

namespace App\Identity\Domain;

use App\Shared\Domain\DomainEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class User extends DomainEntity implements UserInterface, PasswordAuthenticatedUserInterface
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
            email: mb_strtolower($email),
            password: $password,
            role: $role,
            active: true,
            createdAt: new \DateTimeImmutable(),
        );
    }

    public function update(string $email, UserRole $role, bool $active): void
    {
        $this->email = mb_strtolower($email);
        $this->role = $role;
        $this->active = $active;
    }

    public function changePassword(string $password): void
    {
        $this->password = $password;
    }

    public function isAdmin(): bool
    {
        return UserRole::Admin === $this->role;
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

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    #[\Override]
    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    /**
     * @return list<string>
     */
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
}
