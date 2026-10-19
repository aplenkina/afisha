<?php

namespace App\Catalog\Domain;

use App\Shared\Domain\DomainEntityConflict;

final class EventAlreadyImported extends DomainEntityConflict
{
    private const string CODE = '1e7b4c92-8d35-4a06-a7f1-3b2c9e5d6f44';
    private const string MESSAGE = 'Событие %s из источника %d уже существует';

    public function __construct(
        private readonly int $sourceId,
        private readonly string $externalId,
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
        return sprintf(self::MESSAGE, $this->externalId, $this->sourceId);
    }
}
