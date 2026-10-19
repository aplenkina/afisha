<?php

namespace App\Catalog\Application\UpdateSource;

use App\Shared\Bus\Command\CommandInterface;

final readonly class UpdateSourceCommand implements CommandInterface
{
    public function __construct(
        public int $id,
        public int $cityId,
        public string $name,
        public ?string $slug,
        public string $sourceUrl,
        public ?array $config = null,
    ) {
    }
}
