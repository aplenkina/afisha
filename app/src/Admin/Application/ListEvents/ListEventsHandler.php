<?php

declare(strict_types=1);

namespace App\Admin\Application\ListEvents;

use App\Shared\Bus\Query\QueryHandlerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ListEventsHandler implements QueryHandlerInterface
{
    private const int PAGE_SIZE = 50;
    public const int REGEX_LIMIT = 200;

    public function __construct(private EventListRepository $events)
    {
    }

    public function __invoke(ListEventsQuery $query): ListEventsResponse
    {
        if ($query->usesRegex()) {
            return new ListEventsResponse($this->events->find($query, self::REGEX_LIMIT, 0), $this->events->categoryNames(), 1, false);
        }

        $page = max(1, min($query->page, intdiv(\PHP_INT_MAX, self::PAGE_SIZE)));
        $events = $this->events->find($query, self::PAGE_SIZE + 1, ($page - 1) * self::PAGE_SIZE);

        return new ListEventsResponse(
            array_slice($events, 0, self::PAGE_SIZE),
            $this->events->categoryNames(),
            $page,
            \count($events) > self::PAGE_SIZE,
        );
    }
}
