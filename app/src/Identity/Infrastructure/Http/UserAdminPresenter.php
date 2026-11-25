<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Http;

use App\Identity\Application\ListUsers\ListUsersResponse;
use App\Identity\Application\UserView;
use App\Identity\Domain\UserRole;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

final readonly class UserAdminPresenter
{
    public function __construct(private Environment $twig, private UrlGeneratorInterface $urls, private RequestStack $requests)
    {
    }

    public function list(ListUsersResponse $result, int $currentId): Response
    {
        return new Response($this->twig->render('admin/user/index.html.twig', [
            'users' => $result->users,
            'currentId' => $currentId,
        ]));
    }

    public function details(?UserView $user): Response
    {
        return new Response($this->twig->render('admin/user/details.html.twig', [
            'user_item' => $user,
            'roles' => UserRole::cases(),
        ]));
    }

    public function saved(): RedirectResponse
    {
        return $this->redirectWithFlash('success', 'Пользователь сохранён', 'admin_user_index');
    }

    public function saveFailed(string $message, ?int $id): RedirectResponse
    {
        if (null === $id) {
            return $this->redirectWithFlash('danger', $message, 'admin_user_new');
        }

        return $this->redirectWithFlash('danger', $message, 'admin_user_details', ['id' => $id]);
    }

    public function deletionResult(?string $error = null): RedirectResponse
    {
        return $this->redirectWithFlash(
            null === $error ? 'success' : 'danger',
            $error ?? 'Пользователь удалён',
            'admin_user_index',
        );
    }

    private function redirectWithFlash(string $type, string $message, string $route, array $parameters = []): RedirectResponse
    {
        $this->requests->getSession()->getFlashBag()->add($type, $message);

        return new RedirectResponse($this->urls->generate($route, $parameters));
    }
}
