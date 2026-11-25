<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Http;

use App\Admin\AbstractAdminController;
use App\Identity\Application\CreateUser\CreateUserCommand;
use App\Identity\Application\DeleteUser\DeleteUserCommand;
use App\Identity\Application\GetUser\GetUserQuery;
use App\Identity\Application\GetUser\GetUserResponse;
use App\Identity\Application\ListUsers\ListUsersQuery;
use App\Identity\Application\UpdateUser\UpdateUserCommand;
use App\Identity\Domain\UserNotFound;
use App\Identity\Domain\UserRole;
use App\Identity\Infrastructure\Security\AuthenticatedUser;
use App\Shared\Domain\DomainError;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AdminRoute('/user', name: 'user')]
#[IsGranted('ROLE_ADMIN')]
final class UserController extends AbstractAdminController
{
    public function __construct(MessageBusInterface $messageBus, private readonly UserAdminPresenter $presenter)
    {
        parent::__construct($messageBus);
    }

    #[AdminRoute('/', name: 'index', options: ['methods' => 'GET'])]
    public function index(#[CurrentUser] AuthenticatedUser $user): Response
    {
        return $this->presenter->list($this->dispatch(new ListUsersQuery()), $user->id);
    }

    #[AdminRoute('/new', name: 'new', options: ['methods' => 'GET'])]
    public function new(): Response
    {
        return $this->presenter->details(null);
    }

    #[AdminRoute('/{id<\d+>}', name: 'details', options: ['methods' => 'GET'])]
    public function details(int $id): Response
    {
        try {
            /** @var GetUserResponse $result */
            $result = $this->dispatch(new GetUserQuery($id));
        } catch (UserNotFound $exception) {
            throw $this->createNotFoundException($exception->getMessage(), $exception);
        }

        return $this->presenter->details($result->user);
    }

    #[AdminRoute('/save', name: 'save', options: ['methods' => 'POST'])]
    public function save(#[MapRequestPayload] UserRequest $request): Response
    {
        $id = $request->userId();
        $role = UserRole::from($request->role);
        $command = null === $id
            ? new CreateUserCommand($request->email, $request->password ?? '', $role)
            : new UpdateUserCommand($id, $request->email, $role, '1' === $request->active, $request->password);

        try {
            $this->dispatch($command);
        } catch (DomainError $error) {
            return $this->presenter->saveFailed($error->getMessage(), $id);
        }

        return $this->presenter->saved();
    }

    #[AdminRoute('/{id<\d+>}', name: 'delete', options: ['methods' => 'DELETE'])]
    public function delete(int $id, #[CurrentUser] AuthenticatedUser $user): Response
    {
        try {
            $this->dispatch(new DeleteUserCommand($id, $user->id));
        } catch (DomainError $error) {
            return $this->presenter->deletionResult($error->getMessage());
        }

        return $this->presenter->deletionResult();
    }
}
