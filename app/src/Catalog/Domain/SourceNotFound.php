<?php

namespace App\Catalog\Domain;

use App\Shared\Domain\DomainEntityNotFound;

final class SourceNotFound extends DomainEntityNotFound
{
    private const string CODE = 'f9afe576-1bd3-489d-97b3-dad432f19286';
    private const string MESSAGE = 'Источник %s не найден';

    public function __construct(
        private readonly int|string $id,
    ) {
        parent::__construct();
    }

    #[\Override]
    public function errorCode(): string
    {
        return self::CODE;
    }

    #[\Override]
    protected function errorMessage(): string
    {
        return sprintf(self::MESSAGE, $this->id);
    }
}
