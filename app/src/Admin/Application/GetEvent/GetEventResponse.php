<?php

declare(strict_types=1);

namespace App\Admin\Application\GetEvent;

use App\Catalog\Domain\Event;
use App\Shared\Bus\Query\ResponseInterface;

final readonly class GetEventResponse implements ResponseInterface
{
    /**
     * @param list<array{id: int, title: string, status: string, starts_at: string}> $duplicates
     * @param list<array<string, mixed>>                                             $comparisons
     */
    public function __construct(
        public Event $event,
        public array $duplicates = [],
        public array $comparisons = [],
    ) {
    }
}
