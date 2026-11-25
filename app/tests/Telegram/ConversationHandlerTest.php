<?php

declare(strict_types=1);

namespace App\Tests\Telegram;

use App\Telegram\Application\ChooseCity;
use App\Telegram\Application\ConversationHandler;
use App\Telegram\Application\ListCities;
use App\Telegram\Application\SubscriberStore;
use App\Telegram\Application\TelegramCatalog;
use PHPUnit\Framework\TestCase;

final class ConversationHandlerTest extends TestCase
{
    public function testListsAvailableCitiesInCatalogOrder(): void
    {
        $catalog = $this->createStub(TelegramCatalog::class);
        $catalog->method('availableCityNames')->willReturn([7 => 'Брест', 2 => 'Минск']);
        $handler = new ConversationHandler($catalog, $this->createStub(SubscriberStore::class));

        self::assertSame([7 => 'Брест', 2 => 'Минск'], $handler->listCities(new ListCities()));
    }

    public function testSavesAvailableCity(): void
    {
        $catalog = $this->createMock(TelegramCatalog::class);
        $catalog->expects(self::once())->method('availableCityName')->with(2)->willReturn('Минск');
        $subscribers = $this->createMock(SubscriberStore::class);
        $subscribers->expects(self::once())->method('selectCity')->with(4_000_000_000, 2);

        $handler = new ConversationHandler($catalog, $subscribers);

        self::assertSame('Минск', $handler->chooseCity(new ChooseCity(4_000_000_000, 2)));
    }

    public function testUnavailableCityDoesNotChangeSubscription(): void
    {
        $catalog = $this->createStub(TelegramCatalog::class);
        $catalog->method('availableCityName')->willReturn(null);
        $subscribers = $this->createMock(SubscriberStore::class);
        $subscribers->expects(self::never())->method('selectCity');

        $handler = new ConversationHandler($catalog, $subscribers);

        self::assertNull($handler->chooseCity(new ChooseCity(42, 2)));
    }
}
