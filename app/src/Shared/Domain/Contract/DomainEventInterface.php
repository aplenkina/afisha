<?php

declare(strict_types=1);

namespace App\Shared\Domain\Contract;

interface DomainEventInterface
{
    public static function eventName(): string;

    public function getAggregateId(): string;

    public function getOccurredOn(): string;

    /**
     * В событии можно передавать:
     * весь агрегат;
     * только данные участвующие в событии;
     * только ид агрегата.
     * Решение зависит от требований. На данный момент у нас передаётся весь агрегат в событии.
     *
     * @return array<array-key, string|array<array-key, string>>
     */
    public function toPrimitives(): array;
}
