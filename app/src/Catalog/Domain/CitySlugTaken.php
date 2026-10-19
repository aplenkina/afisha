<?php

namespace App\Catalog\Domain;

use App\Shared\Domain\DomainEntityConflict;
use Override;

final class CitySlugTaken extends DomainEntityConflict
{
    private const string CODE = '5b8e2f7c-3f0d-4c3b-8d51-2a9a1d7c4e22';
    private const string MESSAGE = 'Город с адресом %s уже существует';

    public function __construct(
        private readonly string $slug,
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
        return sprintf(self::MESSAGE, $this->slug);
    }
}
