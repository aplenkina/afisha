<?php

declare(strict_types=1);

namespace App\Shared\Domain;

use App\Shared\Domain\Contract\DomainEventInterface;

use function sprintf;

class DomainEntity
{
    /**
     * @var array<string, DomainEventInterface>
     */
    protected array $uniqueEvents = [];

    /**
     * @var list<DomainEventInterface>
     */
    protected array $events = [];

    /**
     * Извлечь все события.
     *
     * @return list<DomainEventInterface>
     */
    public function popEvents(): array
    {
        $events = array_merge($this->events, $this->uniqueEvents);

        $this->events = [];
        $this->uniqueEvents = [];

        return array_values($events);
    }

    /**
     * Записать событие.
     */
    protected function record(DomainEventInterface $event): void
    {
        $this->events[] = $event;
    }

    /**
     * Записать уникальное событие.
     */
    protected function recordUnique(DomainEventInterface $event): void
    {
        $key = $this->getEventKey($event);

        $this->uniqueEvents[$key] = $event;
    }

    protected function getEventKey(DomainEventInterface $event): string
    {
        return sprintf('%s_%d', $event::class, $event->getAggregateId());
    }
}
