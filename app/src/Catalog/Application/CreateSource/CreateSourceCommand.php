<?php

namespace App\Catalog\Application\CreateSource;

use App\Shared\Bus\Command\CommandInterface;

final readonly class CreateSourceCommand implements CommandInterface
{
    public function __construct(
        public int $cityId,
        public string $name,
        public ?string $slug,
        public string $sourceUrl,
        public ?array $config = null,
    ) {
    }
}
