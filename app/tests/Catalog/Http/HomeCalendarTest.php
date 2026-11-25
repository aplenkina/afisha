<?php

declare(strict_types=1);

namespace App\Tests\Catalog\Http;

use App\Catalog\Application\ShowCategoryEvents\CategoryEventView;
use App\Catalog\Domain\Category;
use App\Catalog\Domain\City;
use App\Catalog\Infrastructure\Repository\CategoryRepositoryInterface;
use App\Catalog\Infrastructure\Repository\CityRepositoryInterface;
use App\Catalog\Infrastructure\Repository\EventRepositoryInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class HomeCalendarTest extends WebTestCase
{
    public function testHomepageRendersWeekendByDefault(): void
    {
        $client = self::createClient();
        $events = $this->createMock(EventRepositoryInterface::class);
        $events->expects(self::once())->method('findCalendarEvents')->with(
            self::isInstanceOf(\DateTimeImmutable::class),
            self::isInstanceOf(\DateTimeImmutable::class),
            null,
            false,
            null,
            '',
            201,
            0,
        )->willReturn([]);
        $this->setCatalog($events);

        $crawler = $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Афиша города');
        self::assertCount(1, $crawler->filter('input[name="period"][value="weekend"][checked]'));
    }

    public function testCityAndCategoryFiltersRedirectAndSurviveTheRedirect(): void
    {
        $client = self::createClient();
        $client->disableReboot();
        $events = $this->createMock(EventRepositoryInterface::class);
        $events->expects(self::exactly(2))->method('findCalendarEvents')->with(
            self::isInstanceOf(\DateTimeImmutable::class),
            null,
            2,
            true,
            3,
            'джаз',
            201,
            200,
        )->willReturn([]);
        $this->setCatalog($events);

        $client->request('GET', '/?city=minsk&category=music&period=all&free=1&q='.rawurlencode('джаз').'&page=2');
        self::assertResponseRedirects('/minsk/music?q='.rawurlencode('джаз').'&period=all&free=1&page=2');

        $page = $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Музыка · Минск');
        self::assertCount(1, $page->filter('input[name="period"][value="all"][checked]'));
        self::assertCount(1, $page->filter('.calendar-pagination a[rel="prev"]'));
    }

    public function testCityRouteUsesThePathCity(): void
    {
        $client = self::createClient();
        $events = $this->createMock(EventRepositoryInterface::class);
        $events->expects(self::once())->method('findCalendarEvents')->with(
            self::isInstanceOf(\DateTimeImmutable::class),
            null,
            2,
        )->willReturn([new CategoryEventView(
            7, 'Джазовый концерт', 'jazz', new \DateTimeImmutable('2034-12-05 21:00 UTC'),
            null, 'Минск', 'Сцена', null, null, null, true, null, null,
        )]);
        $this->setCatalog($events);

        $client->request('GET', '/minsk?city=unknown&period=all');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Афиша города · Минск');
        self::assertSelectorTextContains('#day-2034-12-06', '6 декабря');
        self::assertSelectorTextContains('.calendar-event-title a[href="/event/jazz"]', 'Джазовый концерт');
    }

    #[DataProvider('invalidRequests')]
    public function testInvalidCalendarRequestsReturn4xx(string $path, int $status): void
    {
        $client = self::createClient();
        $this->setCatalog($this->createStub(EventRepositoryInterface::class));

        $client->request('GET', $path);

        self::assertResponseStatusCodeSame($status);
    }

    public static function invalidRequests(): iterable
    {
        yield 'unknown path city' => ['/unknown-city', 404];
        yield 'unknown path category' => ['/minsk/unknown-category', 404];
        yield 'non-numeric page' => ['/?page=abc', 400];
        yield 'array instead of filter' => ['/?city[]=minsk', 400];
    }

    public function testCustomDatesDisplayValidationErrorsInTheCalendar(): void
    {
        $client = self::createClient();
        $events = $this->createMock(EventRepositoryInterface::class);
        $events->expects(self::never())->method('findCalendarEvents');
        $this->setCatalog($events);

        $client->request('GET', '/?period=custom&from=2034-12-06&to=2034-12-05');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.calendar-date-range [role="alert"]', 'Дата окончания должна быть не раньше даты начала.');
    }

    private function setCatalog(EventRepositoryInterface $events): void
    {
        $city = $this->createConfiguredStub(City::class, ['getId' => 2, 'getSlug' => 'minsk', 'getName' => 'Минск', 'isAvailable' => true]);
        $category = $this->createConfiguredStub(Category::class, ['getId' => 3, 'getAlias' => 'music', 'getName' => 'Музыка']);
        $cities = $this->createStub(CityRepositoryInterface::class);
        $cities->method('findAll')->willReturn([$city]);
        $categories = $this->createStub(CategoryRepositoryInterface::class);
        $categories->method('findAll')->willReturn([$category]);

        self::getContainer()->set(CityRepositoryInterface::class, $cities);
        self::getContainer()->set(CategoryRepositoryInterface::class, $categories);
        self::getContainer()->set(EventRepositoryInterface::class, $events);
    }
}
