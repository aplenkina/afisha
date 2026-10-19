<?php

namespace App\Admin\Application\GetUser;

use App\Shared\Bus\Query\QueryInterface;

final readonly class GetUserQuery implements QueryInterface
{
    public function __construct(
        public int $id,
    ) {
    }
}
