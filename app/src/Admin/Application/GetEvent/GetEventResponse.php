<?php

namespace App\Admin\Application\GetEvent;

use App\Catalog\Domain\Event;
use App\Shared\Bus\Query\ResponseInterface;

final readonly class GetEventResponse implements ResponseInterface
{
    public function __construct(
        public Event $event,
    ) {
    }
}
