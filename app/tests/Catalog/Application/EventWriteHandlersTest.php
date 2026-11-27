<?php

declare(strict_types=1);

namespace App\Tests\Catalog\Application;

use App\Catalog\Application\CreateEvent\CreateEventCommand;
use App\Catalog\Application\CreateEvent\CreateEventHandler;
use App\Catalog\Application\EventEditing\EventEditingRepository;
use App\Catalog\Application\EventEditing\EventReferences;
use App\Catalog\Application\UpdateEvent\UpdateEventCommand;
use App\Catalog\Application\UpdateEvent\UpdateEventHandler;
use App\Catalog\Application\UpdateEvent\UpdateEventSession;
use App\Catalog\Domain\Event;
use App\Catalog\Domain\EventAlreadyImported;
use App\Catalog\Domain\EventSession;
use App\Catalog\Domain\InvalidEventSession;
use App\Shared\Application\FlusherInterface;
use App\Shared\Application\SlugGeneratorInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class EventWriteHandlersTest extends TestCase
{
    #[DataProvider('invalidSessions')]
    public function testInvalidUpdateLeavesEventUntouched(string $reason): void
    {
        $event = Event::create('До изменения', 'event-before', 1, 2);
        $start = new \DateTimeImmutable('2027-02-19T19:00:00+03:00');
        $session = $event->addSession($start);
        new \ReflectionProperty(EventSession::class, 'id')->setValue($session, 17);

        $events = $this->createStub(EventEditingRepository::class);
        $events->method('findById')->willReturn($event);
        $flusher = $this->createMock(FlusherInterface::class);
        $flusher->expects(self::never())->method('flush');

        $updated = $start->modify('+1 day');
        $first = new UpdateEventSession($updated, id: 17);
        $second = match ($reason) {
            'foreign' => new UpdateEventSession($updated, id: 90),
            'duplicate' => new UpdateEventSession($updated, id: 17),
            'chronology' => new UpdateEventSession($updated, endsAt: $start),
        };
        $handler = new UpdateEventHandler($events, $this->createStub(EventReferences::class), $flusher);

        $this->expectException(InvalidEventSession::class);
        try {
            $handler(new UpdateEventCommand(47, 'После изменения', 1, 2, sessions: [$first, $second]));
        } finally {
            self::assertSame('До изменения', $event->getTitle());
            self::assertSame([$session], $event->getSessions());
            self::assertSame($start, $session->getStartsAt());
        }
    }

    public static function invalidSessions(): iterable
    {
        yield 'session from another event' => ['foreign'];
        yield 'repeated session ID' => ['duplicate'];
        yield 'end before start' => ['chronology'];
    }

    public function testCreateRefusesPreviouslyImportedIdentity(): void
    {
        $events = $this->createMock(EventEditingRepository::class);
        $events->expects(self::once())->method('existsBySourceExternalId')->with(5, 'event-1')->willReturn(true);
        $events->expects(self::never())->method('save');
        $flusher = $this->createMock(FlusherInterface::class);
        $flusher->expects(self::never())->method('flush');
        $handler = new CreateEventHandler(
            $events,
            $this->createStub(EventReferences::class),
            $this->createStub(SlugGeneratorInterface::class),
            $flusher,
        );

        $this->expectException(EventAlreadyImported::class);
        $handler(new CreateEventCommand('Событие', 1, 2, sourceId: 5, externalId: 'event-1'));
    }
}
