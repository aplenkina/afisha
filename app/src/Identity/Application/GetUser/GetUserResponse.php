<?php

declare(strict_types=1);

namespace App\Identity\Application\GetUser;

use App\Identity\Application\UserView;
use App\Shared\Bus\Query\ResponseInterface;

final readonly class GetUserResponse implements ResponseInterface
{
    public function __construct(
        public UserView $user,
    ) {
    }
}
