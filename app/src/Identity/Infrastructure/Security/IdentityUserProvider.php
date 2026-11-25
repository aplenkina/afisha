<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use App\Identity\Application\UserRepository;
use App\Identity\Domain\UserNotFound;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/** @implements UserProviderInterface<AuthenticatedUser> */
final readonly class IdentityUserProvider implements UserProviderInterface
{
    public function __construct(private UserRepository $users)
    {
    }

    #[\Override]
    public function loadUserByIdentifier(string $identifier): AuthenticatedUser
    {
        $user = $this->users->findByEmail($identifier);
        if (null === $user) {
            throw $this->notFound($identifier);
        }

        return AuthenticatedUser::fromUser($user);
    }

    #[\Override]
    public function refreshUser(UserInterface $user): AuthenticatedUser
    {
        if (!$user instanceof AuthenticatedUser) {
            throw new UnsupportedUserException();
        }

        try {
            $account = $this->users->findById($user->id);
        } catch (UserNotFound $error) {
            throw $this->notFound($user->getUserIdentifier(), $error);
        }

        if (!$account->isActive()) {
            throw $this->notFound($user->getUserIdentifier());
        }

        return AuthenticatedUser::fromUser($account);
    }

    #[\Override]
    public function supportsClass(string $class): bool
    {
        return AuthenticatedUser::class === $class;
    }

    private function notFound(string $identifier, ?UserNotFound $previous = null): UserNotFoundException
    {
        $error = new UserNotFoundException(previous: $previous);
        $error->setUserIdentifier($identifier);

        return $error;
    }
}
