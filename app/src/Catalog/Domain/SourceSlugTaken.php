<?php

namespace App\Catalog\Domain;

use App\Shared\Domain\DomainEntityConflict;

final class SourceSlugTaken extends DomainEntityConflict
{
    private const string CODE = '0e16837d-6f7b-4174-bff3-6417bfdea44e';
    private const string MESSAGE = 'Источник с адресом %s уже существует';

    public function __construct(
        private readonly string $slug,
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
        return sprintf(self::MESSAGE, $this->slug);
    }
}
