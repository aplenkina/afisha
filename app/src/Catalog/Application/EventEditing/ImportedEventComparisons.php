<?php

declare(strict_types=1);

namespace App\Catalog\Application\EventEditing;

use App\Catalog\Domain\Event;

interface ImportedEventComparisons
{
    /** @return list<array<string, mixed>> */
    public function forEvent(Event $event): array;
}
