<?php

declare(strict_types=1);

namespace App\Tests\Admin;

use App\Admin\Application\GetEvent\GetEventHandler;
use App\Admin\Application\GetEvent\GetEventQuery;
use App\Catalog\Application\EventEditing\EventEditingRepository;
use App\Catalog\Application\EventEditing\ImportedEventComparisons;
use App\Catalog\Domain\Event;
use PHPUnit\Framework\TestCase;

final class GetEventHandlerTest extends TestCase
{
    public function testModerationDataIsOptionalAndScopedToModeratorRequests(): void
    {
        $event = Event::create('Концерт', 'concert', 1, 2);
        $events = $this->createMock(EventEditingRepository::class);
        $events->method('findById')->willReturn($event);
        $events->expects(self::once())->method('findDuplicateCandidates')->with(47)->willReturn([]);
        $imports = $this->createMock(ImportedEventComparisons::class);
        $imports->expects(self::once())->method('forEvent')->with($event)->willReturn([['id' => 48]]);
        $handler = new GetEventHandler($events, $imports);

        self::assertSame([], $handler(new GetEventQuery(47))->comparisons);
        self::assertSame([['id' => 48]], $handler(new GetEventQuery(47, true))->comparisons);
    }
}
