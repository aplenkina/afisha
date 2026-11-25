<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Http;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Twig\Environment;

final readonly class LoginPresenter
{
    public function __construct(private UrlGeneratorInterface $urls, private Environment $twig)
    {
    }

    public function present(bool $loggedIn, AuthenticationUtils $authentication): Response
    {
        if ($loggedIn) {
            return new RedirectResponse($this->urls->generate('admin'));
        }

        return new Response($this->twig->render('admin/login.html.twig', [
            'last_username' => $authentication->getLastUsername(),
            'error' => $authentication->getLastAuthenticationError(),
        ]));
    }
}
