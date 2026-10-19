<?php

namespace App\Identity\Domain;

use App\Shared\Domain\DomainEntityNotFound;
use Override;

final class UserNotFound extends DomainEntityNotFound
{
    private const string CODE = 'a3c5e7f1-2b4d-4a68-9c0e-1f3b5d7a9c88';
    private const string MESSAGE = 'Пользователь %s не найден';

    public function __construct(
        private readonly int|string $value,
    ) {
        parent::__construct();
    }

    #[Override]
    public function errorCode(): string
    {
        return self::CODE;
    }

    #[Override]
    protected function errorMessage(): string
    {
        return sprintf(self::MESSAGE, $this->value);
    }
}
