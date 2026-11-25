<?php

declare(strict_types=1);

namespace App\Tests\Catalog\Application;

use App\Catalog\Application\ShowCalendar\CalendarShowHandler;
use App\Catalog\Application\ShowCalendar\CalendarShowMessage;
use App\Catalog\Application\ShowCategoryEvents\CategoryEventView;
use App\Catalog\Infrastructure\Repository\CategoryRepositoryInterface;
use App\Catalog\Infrastructure\Repository\CityRepositoryInterface;
use App\Catalog\Infrastructure\Repository\EventRepositoryInterface;
use PHPUnit\Framework\TestCase;

final class CalendarTest extends TestCase
{
    public function testAllDatesGroupsOccurrencesByMinskDayAndPaginates(): void
    {
        $first = $this->occurrence('2034-12-05 19:00:00+00:00');
        $second = $this->occurrence('2034-12-05 21:00:00+00:00');
        $events = $this->createMock(EventRepositoryInterface::class);
        $events->expects(self::once())->method('findCalendarEvents')
            ->with(self::isInstanceOf(\DateTimeImmutable::class), null, null, false, null, '', 201, 200)
            ->willReturn(array_merge([$first], array_fill(0, 199, $second), [$this->occurrence('2034-12-08 19:00:00+00:00')]));
        $calendar = new CalendarShowHandler(
            $this->createStub(CityRepositoryInterface::class),
            $events,
            $this->createStub(CategoryRepositoryInterface::class),
        );

        $view = $calendar(new CalendarShowMessage('all', '', false, page: 2));

        self::assertSame(['2034-12-05', '2034-12-06'], array_keys($view->days));
        self::assertSame($first, $view->days['2034-12-05'][0]);
        self::assertSame($second, $view->days['2034-12-06'][0]);
        self::assertSame(2, $view->page);
        self::assertTrue($view->hasNext);
    }

    private function occurrence(string $start): CategoryEventView
    {
        return new CategoryEventView(
            id: 1,
            title: 'Спектакль',
            slug: 'play',
            startsAt: new \DateTimeImmutable($start),
            endsAt: null,
            cityName: 'Минск',
            venueName: null,
            address: null,
            priceMin: null,
            priceMax: null,
            isFree: false,
            ticketUrl: null,
            sourceUrl: null,
        );
    }
}
