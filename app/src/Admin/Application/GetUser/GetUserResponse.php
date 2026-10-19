<?php

namespace App\Admin\Application\GetUser;

use App\Identity\Domain\User;
use App\Shared\Bus\Query\ResponseInterface;

final readonly class GetUserResponse implements ResponseInterface
{
    public function __construct(
        public User $user,
    ) {
    }
}
