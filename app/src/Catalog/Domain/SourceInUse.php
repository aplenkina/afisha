<?php

namespace App\Catalog\Domain;

use App\Shared\Domain\DomainEntityConflict;

final class SourceInUse extends DomainEntityConflict
{
    private const string CODE = '2e03d4f6-e7e0-4732-9eb3-5b9e06a38061';
    private const string MESSAGE = 'Источник %d нельзя удалить: от него есть события';

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
