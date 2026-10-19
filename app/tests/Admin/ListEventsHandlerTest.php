<?php

declare(strict_types=1);

namespace App\Tests\Admin;

use App\Admin\Application\ListEvents\ListEventsHandler;
use App\Admin\Application\ListEvents\ListEventsQuery;
use App\Catalog\Domain\EventStatus;
use App\Catalog\Infrastructure\Repository\EventRepositoryInterface;
use PHPUnit\Framework\TestCase;

final class ListEventsHandlerTest extends TestCase
{
    public function testCategoryFilterIsPassedToRepositoryTogetherWithOtherFilters(): void
    {
        $repository = $this->createMock(EventRepositoryInterface::class);
        $repository->expects(self::once())
            ->method('findList')
            ->with(EventStatus::Draft, self::anything(), 7, 11)
            ->willReturn([]);

        (new ListEventsHandler($repository))(new ListEventsQuery(EventStatus::Draft, createdBy: 7, categoryId: 11));
    }

    public function testWithoutCategoryNothingIsFiltered(): void
    {
        $repository = $this->createMock(EventRepositoryInterface::class);
        $repository->expects(self::once())
            ->method('findList')
            ->with(null, self::anything(), null, null)
            ->willReturn([]);

        (new ListEventsHandler($repository))(new ListEventsQuery());
    }
}
