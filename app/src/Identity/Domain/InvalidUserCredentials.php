<?php

declare(strict_types=1);

namespace App\Identity\Domain;

use App\Shared\Domain\DomainError;

final class InvalidUserCredentials extends DomainError
{
    public function __construct(private readonly string $reason)
    {
        parent::__construct();
    }

    public function errorCode(): string
    {
        return 'a73ee5dd-8ad4-4e1a-818e-38b870a67ef6';
    }

    protected function errorMessage(): string
    {
        return $this->reason;
    }
}
