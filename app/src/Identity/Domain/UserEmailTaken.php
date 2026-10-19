<?php

namespace App\Identity\Domain;

use App\Shared\Domain\DomainEntityConflict;
use Override;

final class UserEmailTaken extends DomainEntityConflict
{
    private const string CODE = 'd4e6f8a0-3c5e-4b79-8d1f-2a4c6e8b0d99';
    private const string MESSAGE = 'Пользователь с почтой %s уже существует';

    public function __construct(
        private readonly string $value,
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
