<?php

declare(strict_types=1);

namespace App\Tests\Catalog\Application;

use App\Catalog\Application\MergeDuplicateEvents\MergeDuplicateEvents;
use App\Catalog\Domain\Event;
use App\Catalog\Domain\EventStatus;
use App\Catalog\Infrastructure\Repository\EventRepositoryInterface;
use App\Shared\Application\FlusherInterface;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;

final class MergeDuplicateEventsTest extends TestCase
{
    public function testPublishingAnExactRepeatKeepsBothSchedulesIntact(): void
    {
        $original = Event::create('Спектакль', 'original', 1, 1, venueId: 10);
        $original->publish();
        $start = new \DateTimeImmutable('2026-12-05 18:00:00+03:00');
        $original->addSession($start);

        $copy = Event::create('Спектакль', 'duplicate', 1, 1, venueId: 10, ticketUrl: 'https://example.test/ticket');
        $copy->addSession($start);
        $next = $start->modify('+1 day');
        $original->addSession($next);

        $existingDuplicate = Event::create('Спектакль', 'prior-duplicate', 1, 1, venueId: 10);
        $existingDuplicate->markDuplicate($copy);
        $events = $this->createStub(EventRepositoryInterface::class);
        $events->method('findById')->willReturnMap([[1, $original], [2, $copy]]);
        $events->method('findDuplicateCandidates')->willReturnMap([[2, true, [['id' => 1]]]]);
        $events->method('findDuplicatesOf')->willReturn([$existingDuplicate]);
        $database = $this->createStub(Connection::class);
        $database->method('transactional')->willReturnCallback(static fn (\Closure $action) => $action());
        $database->method('fetchOne')->willReturn(1);
        $flusher = $this->createMock(FlusherInterface::class);
        $flusher->expects(self::once())->method('flush');

        $result = (new MergeDuplicateEvents($events, $flusher, $database))->publish(2);

        self::assertFalse($result);
        self::assertSame(EventStatus::Published, $original->getStatus());
        self::assertSame(EventStatus::Duplicate, $copy->getStatus());
        self::assertSame($original, $copy->getDuplicateOf());
        self::assertSame($original, $existingDuplicate->getDuplicateOf());
        self::assertCount(2, $original->getSessions());
        self::assertSame($next, $original->getSessions()[1]->getStartsAt());
        self::assertNull($original->getSessions()[1]->getTicketUrl());
        self::assertCount(1, $copy->getSessions());
    }

    public function testPublishingAnEventWithoutAMatchingScheduleLeavesItVisible(): void
    {
        $event = Event::create('Спектакль', 'another-date', 1, 1, venueId: 10);
        $event->addSession(new \DateTimeImmutable('2026-12-06 18:00:00+03:00'));
        $events = $this->createStub(EventRepositoryInterface::class);
        $events->method('findById')->willReturn($event);
        $events->method('findDuplicateCandidates')->willReturn([]);
        $database = $this->createStub(Connection::class);
        $database->method('transactional')->willReturnCallback(static fn (\Closure $action) => $action());
        $database->method('fetchOne')->willReturn(1);
        $flusher = $this->createMock(FlusherInterface::class);
        $flusher->expects(self::once())->method('flush');

        self::assertTrue((new MergeDuplicateEvents($events, $flusher, $database))->publish(2));
        self::assertSame(EventStatus::Published, $event->getStatus());
        self::assertNull($event->getDuplicateOf());
    }
}
