<?php

namespace App\Admin\Application\ListUsers;

use App\Identity\UserRepositoryInterface;
use App\Shared\Bus\Command\HandlerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ListUsersHandler implements HandlerInterface
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
    ) {
    }

    public function __invoke(ListUsersQuery $query): ListUsersResponse
    {
        return new ListUsersResponse($this->userRepository->findAll());
    }
}
