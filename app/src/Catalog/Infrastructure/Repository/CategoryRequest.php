<?php

namespace App\Catalog\Infrastructure\Repository;

use Symfony\Component\Validator\Constraints as SymfonyAssert;

final class CategoryRequest implements CategoryRequestInterface
{
    public function __construct(
        public ?string $id = null,
        #[SymfonyAssert\Sequentially([
            new SymfonyAssert\NotBlank(),
            new SymfonyAssert\Type('string'),
        ])]
        public string $name = '',
        #[SymfonyAssert\Sequentially([
            new SymfonyAssert\NotBlank(),
            new SymfonyAssert\Type('string'),
        ])]
        public string $alias = '',
    ) {
    }
}
