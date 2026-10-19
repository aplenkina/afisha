<?php

namespace App\Admin\Application\GetSource;

use App\Shared\Bus\Query\QueryInterface;

final readonly class GetSourceQuery implements QueryInterface
{
    public function __construct(
        public int $id,
    ) {
    }
}
