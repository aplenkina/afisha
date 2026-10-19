<?php

namespace App\Catalog\Domain;

use App\Shared\Domain\DomainEntityNotFound;
use Override;

final class CategoryNotFound extends DomainEntityNotFound
{
    private const string CODE = '6f4e825e-b939-42f1-8f33-a65cfa50d221';
    private const string MESSAGE = 'Категория %s не найдена';

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
