<?php

declare(strict_types=1);

namespace App\Admin\Application\EventForm;

use App\Shared\Bus\Query\ResponseInterface;

final readonly class EventFormOptions implements ResponseInterface
{
    /**
     * @param list<array{id: int, name: string}> $cities
     * @param list<array{id: int, name: string}> $venues
     * @param list<array{id: int, name: string}> $categories
     */
    public function __construct(public array $cities, public array $venues, public array $categories)
    {
    }
}
