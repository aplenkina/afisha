<?php

namespace App\Catalog\Domain;

use App\Shared\Domain\DomainEntityConflict;
use Override;

final class CityInUse extends DomainEntityConflict
{
    private const string CODE = 'b2f6a8d4-1c9e-4e07-a35b-8d4f2c6e1a66';
    private const string MESSAGE = 'Город %d нельзя удалить: к нему привязаны площадки или события';

    public function __construct(
        private readonly int $id,
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
