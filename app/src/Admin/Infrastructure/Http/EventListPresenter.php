<?php

declare(strict_types=1);

namespace App\Admin\Infrastructure\Http;

use App\Admin\Application\ListEvents\EventListItem;
use App\Admin\Application\ListEvents\ListEventsHandler;
use App\Admin\Application\ListEvents\ListEventsResponse;
use App\Admin\Request\EventListRequest;
use App\Catalog\Domain\EventStatus;
use App\Shared\Presentation\ListSearch;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

final readonly class EventListPresenter
{
    public function __construct(private Environment $twig)
    {
    }

    public function present(ListEventsResponse $result, EventListRequest $request): Response
    {
        $search = new ListSearch($request->q, $request->regex);
        $usesRegex = $search->regex && '' !== $search->query;
        $events = $usesRegex
            ? $search->filter($result->events, static fn (EventListItem $event): array => [
                $event->title, $event->slug, $event->id, $event->status->label(), $event->city, $event->category,
            ])
            : $result->events;
        $filters = array_filter([
            'status' => $request->status,
            'category' => $request->category,
        ], static fn (string $value): bool => '' !== $value);
        $pageParameters = $filters + $search->parameters();

        return new Response($this->twig->render('admin/event/index.html.twig', [
            'events' => $events,
            'statusFilter' => $request->status,
            'categoryId' => $request->category,
            'statuses' => EventStatus::cases(),
            'categories' => $result->categories,
            'search' => $search,
            'usesRegex' => $usesRegex,
            'regexLimit' => ListEventsHandler::REGEX_LIMIT,
            'page' => $result->page,
            'hasNext' => $result->hasNext,
            'filters' => $filters,
            'pageParameters' => $pageParameters,
            'returnParameters' => $result->page > 1 ? $pageParameters + ['page' => $result->page] : $pageParameters,
        ]));
    }
}
