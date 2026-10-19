<?php

namespace App\Catalog\Domain;

use App\Shared\Domain\DomainEntityNotFound;
use Override;

final class CityNotFound extends DomainEntityNotFound
{
    private const string CODE = '0c1f3d0e-7a56-4a4e-9a0b-6f3f0f2b8a11';
    private const string MESSAGE = 'Город %s не найден';

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
