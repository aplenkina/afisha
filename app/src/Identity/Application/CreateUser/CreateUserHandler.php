<?php

namespace App\Identity\Application\CreateUser;

use App\Identity\Domain\User;
use App\Identity\Domain\UserEmailTaken;
use App\Identity\UserRepositoryInterface;
use App\Shared\Application\FlusherInterface;
use App\Shared\Bus\Command\HandlerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;

#[AsMessageHandler]
final readonly class CreateUserHandler implements HandlerInterface
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private PasswordHasherFactoryInterface $hasherFactory,
        private FlusherInterface $flusher,
    ) {
    }

    public function __invoke(CreateUserCommand $command): int
    {
        if ($this->userRepository->existsByEmail($command->email)) {
            throw new UserEmailTaken(mb_strtolower($command->email));
        }

        $user = User::create(
            email: $command->email,
            password: $this->hasherFactory->getPasswordHasher(User::class)->hash($command->plainPassword),
            role: $command->role,
        );

        $this->userRepository->save($user);
        $this->flusher->flush();

        return $user->getId();
    }
}
