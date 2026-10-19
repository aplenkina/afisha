<?php

namespace App\Identity\Application\DeleteUser;

use App\Shared\Bus\Command\CommandInterface;

final readonly class DeleteUserCommand implements CommandInterface
{
    public function __construct(
        public int $id,
    ) {
    }
}
