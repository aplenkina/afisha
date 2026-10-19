<?php

namespace App\Identity\Domain;

use App\Shared\Domain\DomainEntityConflict;
use Override;

final class LastAdminProtected extends DomainEntityConflict
{
    private const string CODE = 'e5f7a9b1-4d6f-4c8a-9e20-3b5d7f9c1eaa';
    private const string MESSAGE = 'Нельзя убрать, отключить или удалить последнего активного администратора';

    #[Override]
    public function errorCode(): string
    {
        return self::CODE;
    }

    #[Override]
    protected function errorMessage(): string
    {
        return self::MESSAGE;
    }
}
