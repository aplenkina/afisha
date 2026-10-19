<?php

declare(strict_types=1);

namespace App\Shared\Domain;

use Override;

use function sprintf;

class EnumTypeMismatched extends DomainError
{
    /**
     * @param list<string|int> $allowedTypes
     */
    public function __construct(protected array $allowedTypes)
    {
        parent::__construct();
    }

    #[Override]
    public function errorCode(): string
    {
        return '3347128a-7e8b-430b-b28f-79579c46d04e';
    }

    #[Override]
    protected function errorMessage(): string
    {
        return sprintf('Enum должен быть следующих типов: %s.', implode(', ', $this->allowedTypes));
    }
}
