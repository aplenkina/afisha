<?php

namespace App\Catalog\Application\ShowCalendar;

use App\Catalog\Application\ShowCategoryEvents\CategoryEventView;
use App\Catalog\Domain\EventNotFound;
use App\Catalog\Domain\EventStatus;
use App\Catalog\Infrastructure\Repository\CategoryRepositoryInterface;
use App\Catalog\Infrastructure\Repository\CityRepositoryInterface;
use App\Catalog\Infrastructure\Repository\EventRepositoryInterface;
use App\Catalog\Infrastructure\Repository\VenueRepositoryInterface;

final readonly class EventDetails
{
    public function __construct(
        private EventRepositoryInterface $events,
        private CityRepositoryInterface $cities,
        private CategoryRepositoryInterface $categories,
        private VenueRepositoryInterface $venues,
    ) {
    }

    public function show(string $slug): array
    {
        $event = $this->events->findBySlug($slug);
        if (EventStatus::Published !== $event->getStatus()) {
            throw new EventNotFound($slug);
        }

        $city = $this->cities->findById($event->getCityId());
        $category = $this->categories->findById($event->getCategoryId());
        $venues = [];
        foreach ($this->venues->findAll($city->getId()) as $venue) {
            $venues[$venue->getId()] = $venue;
        }

        $sessions = [];
        foreach ($event->getSessions() as $session) {
            $sessions[] = [
                'session' => $session,
                'venue' => $venues[$session->getVenueId() ?? $event->getVenueId()] ?? null,
                'ticketUrl' => self::publicUrl($session->getTicketUrl()) ?? self::publicUrl($event->getTicketUrl()),
            ];
        }
        usort($sessions, static fn (array $left, array $right): int => $left['session']->getStartsAt() <=> $right['session']->getStartsAt());

        return [
            'event' => $event,
            'city' => $city,
            'category' => $category,
            'sessions' => $sessions,
            'imageUrl' => self::publicUrl($event->getImageUrl()),
            'sourceUrl' => self::publicUrl($event->getSourceUrl()),
            'price' => CategoryEventView::formatPriceRange($event->isFree(), $event->getPriceMin(), $event->getPriceMax()),
        ];
    }

    private static function publicUrl(?string $url): ?string
    {
        return null !== $url && 1 === preg_match('#^https?://#i', $url) ? $url : null;
    }
}
