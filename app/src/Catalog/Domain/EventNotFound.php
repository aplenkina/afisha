<?php

namespace App\Catalog\Domain;

use App\Shared\Domain\DomainEntityNotFound;
use Override;

final class EventNotFound extends DomainEntityNotFound
{
    private const string CODE = '9d3a6b14-2c7e-4f58-b1a3-0e6c5d9f7a33';
    private const string MESSAGE = 'Событие %s не найдено';

    public function __construct(
        private readonly int|string $id,
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
        return sprintf(self::MESSAGE, $this->id);
    }
}
