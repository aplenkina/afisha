<?php

namespace App\Admin\Application\GetUser;

use App\Identity\UserRepositoryInterface;
use App\Shared\Bus\Command\HandlerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetUserHandler implements HandlerInterface
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
    ) {
    }

    public function __invoke(GetUserQuery $query): GetUserResponse
    {
        return new GetUserResponse($this->userRepository->findById($query->id));
    }
}
