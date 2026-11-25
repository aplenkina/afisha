<?php

declare(strict_types=1);

namespace App\Identity\Application\GetUser;

use App\Shared\Bus\Query\QueryInterface;

/** @implements QueryInterface<GetUserResponse> */
final readonly class GetUserQuery implements QueryInterface
{
    public function __construct(
        public int $id,
    ) {
    }
}
