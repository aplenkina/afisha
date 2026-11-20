<?php

namespace App\Telegram\Application;

use App\Catalog\Infrastructure\Repository\CategoryRepositoryInterface;
use App\Catalog\Infrastructure\Repository\CityRepositoryInterface;
use App\Catalog\Infrastructure\Repository\EventRepositoryInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class DigestPreview
{
    public function __construct(
        private EventRepositoryInterface $events,
        private CityRepositoryInterface $cities,
        private CategoryRepositoryInterface $categories,
        private UrlGeneratorInterface $urls,
    ) {
    }

    /** @return list<string> */
    public function build(string $citySlug, int $days, bool $onlyFree, ?string $categoryAlias): array
    {
        if (!in_array($days, [7, 14, 30], true)) {
            throw new \InvalidArgumentException('Горизонт должен быть 7, 14 или 30 дней.');
        }
        $city = $this->cities->findBySlug($citySlug);
        if (!$city->isAvailable()) {
            throw new \InvalidArgumentException('Город недоступен в публичной афише.');
        }
        $category = null !== $categoryAlias ? $this->categories->findByAlias($categoryAlias, onlyAvailable: true) : null;
        $timezone = new \DateTimeZone('Europe/Minsk');
        $now = new \DateTimeImmutable('now', $timezone);
        $until = $now->modify('+'.$days.' days');
        $sessions = $this->events->findDigestSessions($now, $until, $city->getId(), $onlyFree, $category?->getId());
        if ([] === $sessions) {
            return [];
        }

        $filters = ['city' => $citySlug, 'period' => 'custom', 'from' => $now->format('Y-m-d'), 'to' => $until->format('Y-m-d')];
        if (null !== $category) {
            $filters['category'] = $category->getAlias();
        }
        if ($onlyFree) {
            $filters['free'] = 1;
        }
        $calendar = $this->urls->generate($category ? 'calendar_city_category' : 'calendar_city', $filters, UrlGeneratorInterface::ABSOLUTE_URL);
        $footer = "\n\nАфиша: ".$calendar;
        $heading = 'Афиша · '.$city->getName().' · '.$days.' дн.';
        $messages = [];
        $message = $heading;
        $lastDay = null;
        foreach ($sessions as $session) {
            $start = $session->startsAt->setTimezone($timezone);
            $day = $start->format('d.m.Y');
            $link = $this->urls->generate('event_page', ['slug' => $session->slug], UrlGeneratorInterface::ABSOLUTE_URL);
            $line = $start->format('H:i').' — '.$session->title;
            $details = array_filter([$session->venueName, $session->priceLabel()]);
            if ([] !== $details) {
                $line .= "\n".implode(' · ', $details);
            }
            $line .= "\n".$link;
            $block = ($day !== $lastDay ? "\n\n".$day : "\n")."\n".$line;
            if (strlen($message.$block.$footer) > 4000) {
                $messages[] = $message.$footer;
                $message = $heading;
                $block = "\n\n".$day."\n".$line;
            }
            $message .= $block;
            $lastDay = $day;
        }
        $messages[] = $message.$footer;

        return $messages;
    }
}
