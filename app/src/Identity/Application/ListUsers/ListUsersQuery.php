<?php

declare(strict_types=1);

namespace App\Identity\Application\ListUsers;

use App\Shared\Bus\Query\QueryInterface;

/** @implements QueryInterface<ListUsersResponse> */
final readonly class ListUsersQuery implements QueryInterface
{
}
