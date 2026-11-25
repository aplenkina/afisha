<?php

declare(strict_types=1);

namespace App\Identity\Application\DeleteUser;

use App\Identity\Application\UserRepository;
use App\Identity\Application\UserTransaction;
use App\Identity\Domain\ActiveAdminPolicy;
use App\Identity\Domain\SelfDeletionForbidden;
use App\Shared\Bus\Command\HandlerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class DeleteUserHandler implements HandlerInterface
{
    public function __construct(private UserRepository $users, private UserTransaction $transaction)
    {
    }

    public function __invoke(DeleteUserCommand $command): void
    {
        if ($command->actorId === $command->id) {
            throw new SelfDeletionForbidden();
        }

        $this->transaction->run(function () use ($command): void {
            $user = $this->users->findById($command->id);
            if ($user->isActiveAdmin()) {
                ActiveAdminPolicy::ensureAdminRemains($this->users->countActiveAdmins());
            }

            $this->users->delete($user);
        });
    }
}
