<?php

declare(strict_types=1);

namespace App\Catalog\Domain;

use App\Shared\Domain\DomainInvalidReference;

final class InvalidEventSession extends DomainInvalidReference
{
    public function __construct(private readonly string $reason)
    {
        parent::__construct();
    }

    public function errorCode(): string
    {
        return '7f9811b6-260f-4be9-a16b-f25ee272605e';
    }

    protected function errorMessage(): string
    {
        return $this->reason;
    }
}
