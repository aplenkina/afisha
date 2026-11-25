<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Doctrine;

use App\Identity\Application\UserRepository;
use App\Identity\Domain\User;
use App\Identity\Domain\UserCredentials;
use App\Identity\Domain\UserNotFound;
use App\Identity\Domain\UserRole;
use App\Shared\Infrastructure\Doctrine\AbstractDoctrineRepository;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(UserRepository::class)]
final readonly class DoctrineUserRepository extends AbstractDoctrineRepository implements UserRepository
{
    #[\Override]
    public function save(User $user): void
    {
        $this->add($user);
    }

    #[\Override]
    public function delete(User $user): void
    {
        $this->remove($user);
    }

    #[\Override]
    public function findById(int $id): User
    {
        return $this->repository(User::class)->find($id) ?? throw new UserNotFound($id);
    }

    #[\Override]
    public function findByEmail(string $email): ?User
    {
        return $this->repository(User::class)->findOneBy(['email' => UserCredentials::normalizeEmail($email)]);
    }

    #[\Override]
    public function existsByEmail(string $email, ?int $exceptId = null): bool
    {
        $found = $this->findByEmail($email);

        return null !== $found && $found->getId() !== $exceptId;
    }

    #[\Override]
    public function findAll(): array
    {
        return $this->repository(User::class)->findBy([], ['email' => 'ASC']);
    }

    #[\Override]
    public function countActiveAdmins(): int
    {
        return $this->repository(User::class)->count(['role' => UserRole::Admin, 'active' => true]);
    }
}
