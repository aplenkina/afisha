<?php

declare(strict_types=1);

namespace App\Catalog\Api;

use App\Catalog\Application\ShowCalendar\CalendarShowResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

final readonly class CalendarPresenter
{
    public function __construct(private UrlGeneratorInterface $urls, private Environment $twig)
    {
    }

    public function present(CalendarShowResponse $calendar, string $currentRoute, ?string $city, ?string $category): Response
    {
        if ((null !== $city && null === $calendar->selectedCity) || (null !== $category && null === $calendar->selectedCategory)) {
            throw new NotFoundHttpException();
        }

        $route = $this->calendarRoute($calendar);
        $params = array_filter([
            'city' => $calendar->selectedCity?->getSlug(),
            'category' => $calendar->selectedCategory?->getAlias(),
            'q' => '' !== $calendar->search ? $calendar->search : null,
        ], static fn (?string $value): bool => null !== $value);

        if ($route !== $currentRoute) {
            return new RedirectResponse($this->urls->generate($route, $params + $this->redirectFilters($calendar)));
        }

        return new Response($this->twig->render('site/home.html.twig', get_object_vars($calendar) + [
            'calendarRoute' => $route,
            'calendarParams' => $params,
        ]));
    }

    private function calendarRoute(CalendarShowResponse $calendar): string
    {
        if (null === $calendar->selectedCity) {
            return 'home';
        }

        return null === $calendar->selectedCategory ? 'calendar_city' : 'calendar_city_category';
    }

    /** @return array<string, int|string> */
    private function redirectFilters(CalendarShowResponse $calendar): array
    {
        $params = [];
        if ('weekend' !== $calendar->period) {
            $params['period'] = $calendar->period;
        }
        if ('custom' === $calendar->period) {
            $params['from'] = $calendar->dateFrom;
            $params['to'] = $calendar->dateTo;
        }
        if ($calendar->onlyFree) {
            $params['free'] = 1;
        }
        if ($calendar->page > 1) {
            $params['page'] = $calendar->page;
        }

        return $params;
    }
}
