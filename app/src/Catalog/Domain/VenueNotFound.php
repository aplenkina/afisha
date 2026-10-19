<?php

namespace App\Catalog\Domain;

use App\Shared\Domain\DomainEntityNotFound;

final class VenueNotFound extends DomainEntityNotFound
{
    private const string CODE = '4a9c7e21-5b38-4d60-8f12-7c3e1a0b9d55';
    private const string MESSAGE = 'Площадка %s не найдена';

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
