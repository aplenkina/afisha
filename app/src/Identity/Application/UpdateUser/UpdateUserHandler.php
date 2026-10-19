<?php

namespace App\Identity\Application\UpdateUser;

use App\Identity\Domain\LastAdminProtected;
use App\Identity\Domain\UserEmailTaken;
use App\Identity\Domain\UserRole;
use App\Identity\UserRepositoryInterface;
use App\Identity\Domain\User;
use App\Shared\Application\FlusherInterface;
use App\Shared\Bus\Command\HandlerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;

#[AsMessageHandler]
final readonly class UpdateUserHandler implements HandlerInterface
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private PasswordHasherFactoryInterface $hasherFactory,
        private FlusherInterface $flusher,
    ) {
    }

    public function __invoke(UpdateUserCommand $command): void
    {
        $user = $this->userRepository->findById($command->id);

        if ($this->userRepository->existsByEmail($command->email, $user->getId())) {
            throw new UserEmailTaken(mb_strtolower($command->email));
        }

        $staysActiveAdmin = $command->role === UserRole::Admin && $command->active;
        if ($user->isAdmin() && $user->isActive() && !$staysActiveAdmin && $this->userRepository->countActiveAdmins() <= 1) {
            throw new LastAdminProtected();
        }

        $user->update($command->email, $command->role, $command->active);

        if ($command->plainPassword !== null && $command->plainPassword !== '') {
            $user->changePassword($this->hasherFactory->getPasswordHasher(User::class)->hash($command->plainPassword));
        }

        $this->flusher->flush();
    }
}
