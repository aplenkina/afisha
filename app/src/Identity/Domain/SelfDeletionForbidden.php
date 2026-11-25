<?php

declare(strict_types=1);

namespace App\Identity\Domain;

use App\Shared\Domain\DomainEntityConflict;

final class SelfDeletionForbidden extends DomainEntityConflict
{
    public function errorCode(): string
    {
        return '28c4c02c-5531-458a-8bc0-13156d9bea5d';
    }

    protected function errorMessage(): string
    {
        return 'Нельзя удалить самого себя';
    }
}
