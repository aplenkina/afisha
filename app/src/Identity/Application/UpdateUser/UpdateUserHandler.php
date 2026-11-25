<?php

declare(strict_types=1);

namespace App\Identity\Application\UpdateUser;

use App\Identity\Application\UserPasswordHasher;
use App\Identity\Application\UserRepository;
use App\Identity\Application\UserTransaction;
use App\Identity\Domain\ActiveAdminPolicy;
use App\Identity\Domain\UserCredentials;
use App\Identity\Domain\UserEmailTaken;
use App\Identity\Domain\UserRole;
use App\Shared\Bus\Command\HandlerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class UpdateUserHandler implements HandlerInterface
{
    public function __construct(
        private UserRepository $users,
        private UserPasswordHasher $passwords,
        private UserTransaction $transaction,
    ) {
    }

    public function __invoke(UpdateUserCommand $command): void
    {
        $email = UserCredentials::requireEmail($command->email);
        $password = $this->hashNewPassword($command->plainPassword);

        $this->transaction->run(function () use ($command, $email, $password): void {
            $user = $this->users->findById($command->id);
            if ($this->users->existsByEmail($email, $user->getId())) {
                throw new UserEmailTaken($email);
            }

            $staysActiveAdmin = UserRole::Admin === $command->role && $command->active;
            if ($user->isActiveAdmin() && !$staysActiveAdmin) {
                ActiveAdminPolicy::ensureAdminRemains($this->users->countActiveAdmins());
            }

            $user->update($email, $command->role, $command->active);
            if (null !== $password) {
                $user->changePassword($password);
            }
        });
    }

    private function hashNewPassword(?string $password): ?string
    {
        if (null === $password || '' === $password) {
            return null;
        }

        UserCredentials::requirePassword($password);

        return $this->passwords->hash($password);
    }
}
