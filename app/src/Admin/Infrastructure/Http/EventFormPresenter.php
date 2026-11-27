<?php

declare(strict_types=1);

namespace App\Admin\Infrastructure\Http;

use App\Admin\Application\EventForm\EventFormOptions;
use App\Catalog\Domain\Event;
use App\Catalog\Domain\SessionStatus;
use App\Shared\Domain\DomainError;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

final readonly class EventFormPresenter
{
    public function __construct(
        private Environment $twig,
        private UrlGeneratorInterface $urls,
        private RequestStack $requests,
    ) {
    }

    public function form(EventFormOptions $options, ?Event $event, array $duplicates = [], array $comparisons = []): Response
    {
        return new Response($this->twig->render('admin/event/details.html.twig', [
            'event' => $event,
            'cities' => $options->cities,
            'venues' => $options->venues,
            'categories' => $options->categories,
            'sessionStatuses' => SessionStatus::cases(),
            'duplicates' => $duplicates,
            'comparisons' => $comparisons,
        ]));
    }

    public function saved(int $id): RedirectResponse
    {
        $this->requests->getSession()->getFlashBag()->add('success', 'Событие сохранено');

        return new RedirectResponse($this->urls->generate('admin_event_details', ['id' => $id]));
    }

    public function failed(DomainError $error, ?int $id): RedirectResponse
    {
        $this->requests->getSession()->getFlashBag()->add('danger', $error->getMessage());

        return new RedirectResponse(null === $id
            ? $this->urls->generate('admin_event_new')
            : $this->urls->generate('admin_event_details', ['id' => $id]));
    }
}
