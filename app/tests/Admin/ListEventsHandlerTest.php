<?php

declare(strict_types=1);

namespace App\Tests\Admin;

use App\Admin\Application\ListEvents\EventListItem;
use App\Admin\Application\ListEvents\EventListRepository;
use App\Admin\Application\ListEvents\ListEventsHandler;
use App\Admin\Application\ListEvents\ListEventsQuery;
use App\Catalog\Domain\EventStatus;
use PHPUnit\Framework\TestCase;

final class ListEventsHandlerTest extends TestCase
{
    public function testSearchAndAccessFiltersReachRepositoryTogetherWithPagination(): void
    {
        $query = new ListEventsQuery(EventStatus::Draft, createdBy: 7, categoryId: 11, search: 'джаз', page: 3);
        $repository = $this->createMock(EventListRepository::class);
        $repository->expects(self::once())->method('find')->with(self::identicalTo($query), 51, 100)->willReturn([]);
        $repository->method('categoryNames')->willReturn([11 => 'Концерты']);

        $result = new ListEventsHandler($repository)($query);

        self::assertSame(3, $result->page);
        self::assertSame([11 => 'Концерты'], $result->categories);
        self::assertFalse($result->hasNext);
    }

    public function testExtraResultSignalsNextPageWithoutRenderingIt(): void
    {
        $query = new ListEventsQuery();
        $event = new EventListItem(47, 'Концерт', 'concert', EventStatus::Published, 'Минск', 'Концерты', 0, null);
        $repository = $this->createMock(EventListRepository::class);
        $repository->expects(self::once())->method('find')->with($query, 51, 0)->willReturn(array_fill(0, 51, $event));

        $result = new ListEventsHandler($repository)($query);

        self::assertCount(50, $result->events);
        self::assertTrue($result->hasNext);
    }

    public function testRegularExpressionKeepsExistingBoundedSearch(): void
    {
        $query = new ListEventsQuery(search: '^Джаз', regex: true, page: 2);
        $repository = $this->createMock(EventListRepository::class);
        $repository->expects(self::once())->method('find')->with($query, ListEventsHandler::REGEX_LIMIT, 0)->willReturn([]);

        $result = new ListEventsHandler($repository)($query);

        self::assertSame(1, $result->page);
        self::assertFalse($result->hasNext);
    }
}
