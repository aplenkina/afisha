<?php

namespace App\Catalog\Domain;

use App\Shared\Domain\DomainEntityConflict;

final class VenueInUse extends DomainEntityConflict
{
    private const string CODE = 'c7d1e9b3-6a24-4f8d-9b50-1e3a7c5d2f77';
    private const string MESSAGE = 'Площадку %d нельзя удалить: к ней привязаны события';

    public function __construct(
        private readonly int $id,
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
