<?php

namespace App\Admin;

use App\Admin\Application\GetUser\GetUserQuery;
use App\Admin\Application\GetUser\GetUserResponse;
use App\Admin\Application\ListUsers\ListUsersQuery;
use App\Admin\Application\ListUsers\ListUsersResponse;
use App\Admin\Request\UserRequest;
use App\Identity\Application\CreateUser\CreateUserCommand;
use App\Identity\Application\DeleteUser\DeleteUserCommand;
use App\Identity\Application\UpdateUser\UpdateUserCommand;
use App\Identity\Domain\User;
use App\Identity\Domain\UserNotFound;
use App\Identity\Domain\UserRole;
use App\Shared\Domain\DomainError;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AdminRoute('/user', name: 'user')]
#[IsGranted('ROLE_ADMIN')]
class UserController extends AbstractAdminController
{
    #[AdminRoute('/', name: 'index', options: ['methods' => 'GET'])]
    public function index(): Response
    {
        /** @var ListUsersResponse $result */
        $result = $this->dispatch(new ListUsersQuery());

        return $this->render('/admin/user/index.html.twig', [
            'users' => $result->users,
            'currentId' => $this->currentUserId(),
        ]);
    }

    #[AdminRoute('/new', name: 'new', options: ['methods' => 'GET'])]
    public function new(): Response
    {
        return $this->render('/admin/user/details.html.twig', ['user_item' => null, 'roles' => UserRole::cases()]);
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

        return $this->render('/admin/user/details.html.twig', ['user_item' => $result->user, 'roles' => UserRole::cases()]);
    }

    #[AdminRoute('/save', name: 'save', options: ['methods' => 'POST'])]
    public function save(#[MapRequestPayload] UserRequest $request): Response
    {
        $id = $this->toInt($request->id);
        $role = UserRole::tryFrom($request->role);

        if ($role === null) {
            $this->addFlash('danger', 'Выберите роль');

            return $this->redirectToRoute($id !== null ? 'admin_user_details' : 'admin_user_new', $id !== null ? ['id' => $id] : []);
        }

        try {
            if ($id === null) {
                if ($this->toString($request->password) === null) {
                    $this->addFlash('danger', 'Укажите пароль');

                    return $this->redirectToRoute('admin_user_new');
                }

                $this->dispatch(new CreateUserCommand($request->email, (string) $request->password, $role));
            } else {
                $this->dispatch(new UpdateUserCommand(
                    id: $id,
                    email: $request->email,
                    role: $role,
                    active: $request->active !== null,
                    plainPassword: $this->toString($request->password),
                ));
            }
        } catch (DomainError $error) {
            return $this->fail($error, $id !== null ? 'admin_user_details' : 'admin_user_new', $id !== null ? ['id' => $id] : []);
        }

        $this->addFlash('success', 'Пользователь сохранён');

        return $this->redirectToRoute('admin_user_index');
    }

    #[AdminRoute('/{id<\d+>}', name: 'delete', options: ['methods' => 'DELETE'])]
    public function delete(int $id): Response
    {
        if ($id === $this->currentUserId()) {
            $this->addFlash('danger', 'Нельзя удалить самого себя');

            return $this->redirectToRoute('admin_user_index');
        }

        try {
            $this->dispatch(new DeleteUserCommand($id));
            $this->addFlash('success', 'Пользователь удалён');
        } catch (DomainError $error) {
            return $this->fail($error, 'admin_user_index');
        }

        return $this->redirectToRoute('admin_user_index');
    }

    private function currentUserId(): ?int
    {
        $user = $this->getUser();

        return $user instanceof User ? $user->getId() : null;
    }
}
