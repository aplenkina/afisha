<?php

declare(strict_types=1);

namespace App\Identity\Application\CreateUser;

use App\Identity\Application\UserPasswordHasher;
use App\Identity\Application\UserRepository;
use App\Identity\Application\UserTransaction;
use App\Identity\Domain\User;
use App\Identity\Domain\UserCredentials;
use App\Identity\Domain\UserEmailTaken;
use App\Shared\Bus\Command\HandlerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class CreateUserHandler implements HandlerInterface
{
    public function __construct(
        private UserRepository $users,
        private UserPasswordHasher $passwords,
        private UserTransaction $transaction,
    ) {
    }

    public function __invoke(CreateUserCommand $command): int
    {
        $email = UserCredentials::requireEmail($command->email);
        UserCredentials::requirePassword($command->plainPassword);
        $password = $this->passwords->hash($command->plainPassword);

        $user = $this->transaction->run(function () use ($command, $email, $password): User {
            if ($this->users->existsByEmail($email)) {
                throw new UserEmailTaken($email);
            }

            $user = User::create($email, $password, $command->role);
            $this->users->save($user);

            return $user;
        });

        return $user->getId();
    }
}
