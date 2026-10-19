<?php

declare(strict_types=1);

namespace App\Tests\Catalog\Application;

use App\Catalog\Application\ShowCategoryEvents\CategoryEventView;
use App\Catalog\Application\ShowCategoryEvents\ShowCategoryEventsHandler;
use App\Catalog\Application\ShowCategoryEvents\ShowCategoryEventsQuery;
use App\Catalog\Infrastructure\Repository\EventRepositoryInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ShowCategoryEventsTest extends TestCase
{
    /**
     * @return iterable<string, array{?string, ?string, bool, ?string}>
     */
    public static function prices(): iterable
    {
        yield 'диапазон' => ['100.00', '220.00', false, '100 – 220 BYN'];
        yield 'одна цена' => ['80.00', '80.00', false, 'от 80 BYN'];
        yield 'только минимум' => ['15.50', null, false, 'от 15,50 BYN'];
        yield 'только максимум' => [null, '40.00', false, 'до 40 BYN'];
        yield 'цены нет' => [null, null, false, null];
        yield 'бесплатно главнее цены' => ['0.00', '0.00', true, 'Бесплатно'];
    }

    #[DataProvider('prices')]
    public function testPriceLabel(?string $min, ?string $max, bool $free, ?string $expected): void
    {
        self::assertSame($expected, $this->view($min, $max, $free)->priceLabel());
    }

    public function testHandlerAsksRepositoryForUpcomingPublishedEventsOfCategory(): void
    {
        $view = $this->view('10.00', null, false);
        $repository = $this->createMock(EventRepositoryInterface::class);
        $repository->expects(self::once())
            ->method('findUpcomingPublishedByCategory')
            ->with(11, self::isInstanceOf(\DateTimeImmutable::class), 50)
            ->willReturn([$view]);

        $response = (new ShowCategoryEventsHandler($repository))(new ShowCategoryEventsQuery(categoryId: 11, limit: 50));

        self::assertSame([$view], $response->events);
    }

    private function view(?string $min, ?string $max, bool $free): CategoryEventView
    {
        return new CategoryEventView(
            id: 1,
            title: 'Концерт',
            slug: 'koncert',
            startsAt: new \DateTimeImmutable('2026-11-01 19:00:00+03'),
            endsAt: null,
            cityName: 'Минск',
            venueName: 'Клуб',
            address: null,
            priceMin: $min,
            priceMax: $max,
            isFree: $free,
            ticketUrl: null,
            sourceUrl: null,
        );
    }
}
