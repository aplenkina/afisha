<?php

declare(strict_types=1);

namespace App\Identity\Application\ListUsers;

use App\Identity\Application\UserView;
use App\Shared\Bus\Query\ResponseInterface;

final readonly class ListUsersResponse implements ResponseInterface
{
    /**
     * @param list<UserView> $users
     */
    public function __construct(
        public array $users,
    ) {
    }
}
