<?php

namespace App\Catalog\Api;

use App\Catalog\Application\ShowCalendar\EventDetails;
use App\Catalog\Domain\EventNotFound;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class EventController extends AbstractController
{
    #[Route('/event/{slug}', name: 'event_page', methods: ['GET'])]
    public function show(string $slug, EventDetails $details): Response
    {
        try {
            return $this->render('site/event.html.twig', $details->show($slug));
        } catch (EventNotFound $exception) {
            throw $this->createNotFoundException('Событие не найдено', $exception);
        }
    }
}
