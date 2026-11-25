<?php

declare(strict_types=1);

namespace App\Identity\Application\GetUser;

use App\Identity\Application\UserRepository;
use App\Identity\Application\UserView;
use App\Shared\Bus\Query\QueryHandlerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetUserHandler implements QueryHandlerInterface
{
    public function __construct(
        private UserRepository $userRepository,
    ) {
    }

    public function __invoke(GetUserQuery $query): GetUserResponse
    {
        return new GetUserResponse(UserView::fromUser($this->userRepository->findById($query->id)));
    }
}
