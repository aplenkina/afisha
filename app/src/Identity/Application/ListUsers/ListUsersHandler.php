<?php

declare(strict_types=1);

namespace App\Identity\Application\ListUsers;

use App\Identity\Application\UserRepository;
use App\Identity\Application\UserView;
use App\Shared\Bus\Query\QueryHandlerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ListUsersHandler implements QueryHandlerInterface
{
    public function __construct(
        private UserRepository $userRepository,
    ) {
    }

    public function __invoke(ListUsersQuery $query): ListUsersResponse
    {
        return new ListUsersResponse(array_map(UserView::fromUser(...), $this->userRepository->findAll()));
    }
}
