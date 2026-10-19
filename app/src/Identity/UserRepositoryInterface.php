<?php

namespace App\Identity;

use App\Identity\Domain\User;
use App\Identity\Domain\UserNotFound;

interface UserRepositoryInterface
{
    public function save(User $user): void;

    public function delete(User $user): void;

    /**
     * @throws UserNotFound
     */
    public function findById(int $id): User;

    public function existsByEmail(string $email, ?int $exceptId = null): bool;

    /**
     * @return list<User>
     */
    public function findAll(): array;

    public function countActiveAdmins(): int;
}
