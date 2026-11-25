<?php

declare(strict_types=1);

namespace App\Catalog\Api;

use App\Catalog\Api\Requests\CalendarRequest;
use App\Catalog\Application\ShowCalendar\CalendarShowMessage;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController
{
    use HandleTrait;

    public function __construct(MessageBusInterface $messageBus, private readonly CalendarPresenter $presenter)
    {
        $this->messageBus = $messageBus;
    }

    #[Route('/', name: 'home', methods: [Request::METHOD_GET])]
    #[Route('/{city<(?!(?:admin|category|event)(?:/|$))[\w-]+>}/{category<[\w&-]+>}', name: 'calendar_city_category', methods: [Request::METHOD_GET], priority: -100)]
    #[Route('/{city<(?!(?:admin|category|event)(?:/|$))[\w-]+>}', name: 'calendar_city', methods: [Request::METHOD_GET], priority: -100)]
    public function __invoke(
        Request $request,
        #[MapQueryString(validationFailedStatusCode: Response::HTTP_BAD_REQUEST)]
        CalendarRequest $filters = new CalendarRequest(),
    ): Response {
        $city = $request->attributes->get('city');
        $category = $request->attributes->get('category');
        $calendar = $this->handle(new CalendarShowMessage(
            period: $filters->period,
            citySlug: $city ?? $filters->city,
            onlyFree: $filters->free,
            categoryAlias: $category ?? $filters->category,
            dateFrom: $filters->from,
            dateTo: $filters->to,
            search: $filters->q,
            page: $filters->page,
        ));

        return $this->presenter->present($calendar, $request->attributes->getString('_route'), $city, $category);
    }
}
