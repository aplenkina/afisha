<?php

namespace App\Identity;

use App\Identity\Domain\User;
use App\Identity\Domain\UserNotFound;
use App\Identity\Domain\UserRole;
use App\Shared\Infrastructure\Doctrine\AbstractDoctrineRepository;
use Override;

readonly class UserRepository extends AbstractDoctrineRepository implements UserRepositoryInterface
{
    #[Override]
    public function save(User $user): void
    {
        $this->add($user);
    }

    #[Override]
    public function delete(User $user): void
    {
        $this->remove($user);
    }

    #[Override]
    public function findById(int $id): User
    {
        return $this->repository(User::class)->find($id) ?? throw new UserNotFound($id);
    }

    #[Override]
    public function existsByEmail(string $email, ?int $exceptId = null): bool
    {
        $found = $this->repository(User::class)->findOneBy(['email' => mb_strtolower($email)]);

        return $found !== null && $found->getId() !== $exceptId;
    }

    #[Override]
    public function findAll(): array
    {
        return $this->repository(User::class)->findBy([], ['email' => 'ASC']);
    }

    #[Override]
    public function countActiveAdmins(): int
    {
        return $this->repository(User::class)->count(['role' => UserRole::Admin, 'active' => true]);
    }
}
