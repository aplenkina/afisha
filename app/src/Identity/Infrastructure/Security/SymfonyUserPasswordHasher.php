<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use App\Identity\Application\UserPasswordHasher;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;

#[AsAlias(UserPasswordHasher::class)]
final readonly class SymfonyUserPasswordHasher implements UserPasswordHasher
{
    public function __construct(private PasswordHasherFactoryInterface $hasherFactory)
    {
    }

    public function hash(string $plainPassword): string
    {
        return $this->hasherFactory->getPasswordHasher(AuthenticatedUser::class)->hash($plainPassword);
    }
}
