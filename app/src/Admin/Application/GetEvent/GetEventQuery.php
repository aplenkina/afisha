<?php

declare(strict_types=1);

namespace App\Admin\Application\GetEvent;

use App\Shared\Bus\Query\QueryInterface;

/** @implements QueryInterface<GetEventResponse> */
final readonly class GetEventQuery implements QueryInterface
{
    public function __construct(
        public int $id,
        public bool $includeModeration = false,
    ) {
    }
}
