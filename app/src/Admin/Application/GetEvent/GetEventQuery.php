<?php

namespace App\Admin\Application\GetEvent;

use App\Shared\Bus\Query\QueryInterface;

final readonly class GetEventQuery implements QueryInterface
{
    public function __construct(
        public int $id,
    ) {
    }
}
