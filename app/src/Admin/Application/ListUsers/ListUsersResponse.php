<?php

namespace App\Admin\Application\ListUsers;

use App\Identity\Domain\User;
use App\Shared\Bus\Query\ResponseInterface;

final readonly class ListUsersResponse implements ResponseInterface
{
    /**
     * @param list<User> $users
     */
    public function __construct(
        public array $users,
    ) {
    }
}
