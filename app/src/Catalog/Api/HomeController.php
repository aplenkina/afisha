<?php

namespace App\Catalog\Api;

use App\Catalog\Application\ShowCalendar\Calendar;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'home', methods: [Request::METHOD_GET])]
    public function index(Request $request, Calendar $calendar): Response
    {
        return $this->renderCalendar($request, $calendar);
    }

    #[Route('/{city<(?!(?:admin|category|event)(?:/|$))[\w-]+>}/{category<[\w&-]+>}', name: 'calendar_city_category', methods: [Request::METHOD_GET], priority: -100)]
    public function cityCategory(Request $request, Calendar $calendar, string $city, string $category): Response
    {
        return $this->renderCalendar($request, $calendar, $city, $category);
    }

    #[Route('/{city<(?!(?:admin|category|event)(?:/|$))[\w-]+>}', name: 'calendar_city', methods: [Request::METHOD_GET], priority: -100)]
    public function city(Request $request, Calendar $calendar, string $city): Response
    {
        return $this->renderCalendar($request, $calendar, $city);
    }

    private function renderCalendar(Request $request, Calendar $calendar, ?string $city = null, ?string $category = null): Response
    {
        $view = $calendar->show(
            $request->query->getString('period', 'weekend'),
            $city ?? $request->query->getString('city'),
            $request->query->getBoolean('free'),
            $category ?? $request->query->getString('category'),
            $request->query->getString('from'),
            $request->query->getString('to'),
            $request->query->getString('q'),
            $request->query->getInt('page', 1),
        );

        if ((null !== $city && null === $view['selectedCity']) || (null !== $category && null === $view['selectedCategory'])) {
            throw $this->createNotFoundException();
        }

        $route = 'home';
        $params = [];
        if (null !== $view['selectedCity']) {
            $route = 'calendar_city';
            $params['city'] = $view['selectedCity']->getSlug();
        }
        if (null !== $view['selectedCategory']) {
            $params['category'] = $view['selectedCategory']->getAlias();
            if (null !== $view['selectedCity']) {
                $route = 'calendar_city_category';
            }
        }

        if ('' !== $view['search']) {
            $params['q'] = $view['search'];
        }

        if ($route !== $request->attributes->get('_route')) {
            return $this->redirectToRoute($route, $params
                + ('weekend' !== $view['period'] ? ['period' => $view['period']] : [])
                + ('custom' === $view['period'] ? ['from' => $view['dateFrom'], 'to' => $view['dateTo']] : [])
                + ($view['onlyFree'] ? ['free' => 1] : [])
                + ($view['page'] > 1 ? ['page' => $view['page']] : []));
        }

        return $this->render('site/home.html.twig', $view + ['calendarRoute' => $route, 'calendarParams' => $params]);
    }
}
