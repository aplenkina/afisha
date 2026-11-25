<?php

declare(strict_types=1);

namespace App\Telegram\Infrastructure\Console;

use App\Catalog\Application\ShowCategoryEvents\CategoryEventView;
use App\Telegram\Application\Digest;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class DigestPresenter
{
    private const int MESSAGE_LIMIT = 4000;

    public function __construct(private UrlGeneratorInterface $urls)
    {
    }

    /** @return list<string> */
    public function format(Digest $digest): array
    {
        if ([] === $digest->sessions) {
            return [];
        }

        $heading = 'Афиша · '.$digest->cityName.' · '.$digest->days.' дн.';

        return $this->formatMessages($digest, $heading, $this->formatCalendarFooter($digest));
    }

    private function formatCalendarFooter(Digest $digest): string
    {
        $filters = [
            'city' => $digest->citySlug,
            'period' => 'custom',
            'from' => $digest->from->format('Y-m-d'),
            'to' => $digest->until->format('Y-m-d'),
        ];
        if (null !== $digest->categoryAlias) {
            $filters['category'] = $digest->categoryAlias;
        }
        if ($digest->onlyFree) {
            $filters['free'] = 1;
        }

        $route = null === $digest->categoryAlias ? 'calendar_city' : 'calendar_city_category';

        return "\n\nАфиша: ".$this->urls->generate($route, $filters, UrlGeneratorInterface::ABSOLUTE_URL);
    }

    /** @return list<string> */
    private function formatMessages(Digest $digest, string $heading, string $footer): array
    {
        $messages = [];
        $message = $heading;
        $lastDay = null;
        $timezone = $digest->from->getTimezone();

        foreach ($digest->sessions as $session) {
            $start = $session->startsAt->setTimezone($timezone);
            $day = $start->format('d.m.Y');
            $dayHeading = "\n\n".$day;
            $entry = "\n".$this->formatSession($session, $start);
            $block = ($day !== $lastDay ? $dayHeading : "\n").$entry;

            if (strlen($message.$block.$footer) > self::MESSAGE_LIMIT) {
                $messages[] = $message.$footer;
                $message = $heading;
                $block = $dayHeading.$entry;
            }

            $message .= $block;
            $lastDay = $day;
        }
        $messages[] = $message.$footer;

        return $messages;
    }

    private function formatSession(CategoryEventView $session, \DateTimeImmutable $start): string
    {
        $link = $this->urls->generate('event_page', ['slug' => $session->slug], UrlGeneratorInterface::ABSOLUTE_URL);
        $line = $start->format('H:i').' — '.$session->title;
        $details = array_filter([$session->venueName, $session->priceLabel()]);
        if ([] !== $details) {
            $line .= "\n".implode(' · ', $details);
        }

        return $line."\n".$link;
    }
}
