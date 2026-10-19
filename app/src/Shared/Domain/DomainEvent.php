<?php

declare(strict_types=1);

namespace App\Shared\Domain;

use App\Shared\Domain\Contract\DomainEventInterface;

abstract readonly class DomainEvent implements DomainEventInterface
{
    protected const string DEFAULT_AGGREGATE_ID = '0';

    private string $occurredOn;

    public function __construct(
        private ?string $aggregateId = self::DEFAULT_AGGREGATE_ID,
        ?string $occurredOn = null,
    ) {
        $this->occurredOn = $occurredOn ?: date('Y-m-d H:i:s');
    }

    #[\Override]
    public function getAggregateId(): string
    {
        return $this->aggregateId;
    }

    #[\Override]
    public function getOccurredOn(): string
    {
        return $this->occurredOn;
    }
}
