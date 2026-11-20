<?php

namespace App\Catalog\Application\ShowCalendar;

use App\Catalog\Domain\City;
use App\Catalog\Infrastructure\Repository\CategoryRepositoryInterface;
use App\Catalog\Infrastructure\Repository\CityRepositoryInterface;
use App\Catalog\Infrastructure\Repository\EventRepositoryInterface;

final readonly class Calendar
{
    private const int PAGE_SIZE = 200;

    public function __construct(
        private CityRepositoryInterface $cities,
        private EventRepositoryInterface $events,
        private CategoryRepositoryInterface $categories,
    ) {
    }

    public function show(string $period, string $citySlug, bool $onlyFree, string $categoryAlias = '', string $dateFrom = '', string $dateTo = '', string $search = '', int $page = 1): array
    {
        $search = mb_substr(trim($search), 0, 120);
        $zone = new \DateTimeZone('Europe/Minsk');
        $now = new \DateTimeImmutable('now', $zone);
        $today = $now->setTime(0, 0);
        $weekday = (int) $today->format('N');
        $saturday = $today->modify(sprintf('%+d days', 6 - $weekday));
        $period = in_array($period, ['today', 'tomorrow', 'weekend', 'week', 'all', 'custom'], true) ? $period : 'weekend';
        [$from, $until] = match ($period) {
            'today' => [$today, $today->modify('+1 day')],
            'tomorrow' => [$today->modify('+1 day'), $today->modify('+2 days')],
            'week' => [$today, $today->modify('+7 days')],
            'all' => [$today, null],
            default => [max($today, $saturday), $saturday->modify('+2 days')],
        };
        $dateError = null;
        if ('custom' === $period) {
            $from = self::parseDate($dateFrom, $zone);
            $lastDay = self::parseDate($dateTo, $zone);
            if (null === $from || null === $lastDay) {
                $dateError = 'Укажи дату начала и дату окончания.';
            } elseif ($from > $lastDay) {
                $dateError = 'Дата окончания должна быть не раньше даты начала.';
            } else {
                $until = $lastDay->modify('+1 day');
            }
        }

        $cities = array_filter($this->cities->findAll(), static fn (City $city): bool => $city->isAvailable());
        $selectedCity = null;
        foreach ($cities as $city) {
            if ($city->getSlug() === $citySlug) {
                $selectedCity = $city;
                break;
            }
        }

        $categories = [];
        $selectedCategory = null;
        foreach ($this->categories->findAll(onlyAvailable: true) as $category) {
            $categories[] = $category;
            if ($category->getAlias() === $categoryAlias) {
                $selectedCategory = $category;
            }
        }

        $days = [];
        $hasNext = false;
        $page = max(1, min($page, intdiv(PHP_INT_MAX, self::PAGE_SIZE)));
        if (null === $dateError && (null === $until || max($from, $now) < $until)) {
            $events = $this->events->findCalendarEvents(
                max($from, $now), $until, $selectedCity?->getId(), $onlyFree, $selectedCategory?->getId(), $search,
                limit: self::PAGE_SIZE + 1, offset: ($page - 1) * self::PAGE_SIZE,
            );
            $hasNext = count($events) > self::PAGE_SIZE;
            foreach (array_slice($events, 0, self::PAGE_SIZE) as $event) {
                $days[$event->startsAt->setTimezone($zone)->format('Y-m-d')][] = $event;
            }
        }

        return compact('cities', 'selectedCity', 'categories', 'selectedCategory', 'period', 'onlyFree', 'days', 'dateFrom', 'dateTo', 'dateError', 'search', 'page', 'hasNext');
    }

    private static function parseDate(string $value, \DateTimeZone $zone): ?\DateTimeImmutable
    {
        $value = trim($value);
        if (preg_match('/^([0-9]{2})\.([0-9]{2})\.([0-9]{4})$/D', $value, $parts)) {
            $value = $parts[3].'-'.$parts[2].'-'.$parts[1];
        }
        if (!preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D', $value) || str_starts_with($value, '0000-')) {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value, $zone);

        return false !== $date && $date->format('Y-m-d') === $value ? $date : null;
    }
}
