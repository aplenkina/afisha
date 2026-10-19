<?php

namespace App\Identity\Application\DeleteUser;

use App\Identity\Domain\LastAdminProtected;
use App\Identity\UserRepositoryInterface;
use App\Shared\Application\FlusherInterface;
use App\Shared\Bus\Command\HandlerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class DeleteUserHandler implements HandlerInterface
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private FlusherInterface $flusher,
    ) {
    }

    public function __invoke(DeleteUserCommand $command): void
    {
        $user = $this->userRepository->findById($command->id);

        if ($user->isAdmin() && $user->isActive() && $this->userRepository->countActiveAdmins() <= 1) {
            throw new LastAdminProtected();
        }

        $this->userRepository->delete($user);
        $this->flusher->flush();
    }
}
