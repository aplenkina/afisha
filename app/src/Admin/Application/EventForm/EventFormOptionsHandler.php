<?php

declare(strict_types=1);

namespace App\Admin\Application\EventForm;

use App\Shared\Bus\Query\QueryHandlerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class EventFormOptionsHandler implements QueryHandlerInterface
{
    public function __construct(private EventFormOptionsRepository $repository)
    {
    }

    public function __invoke(EventFormOptionsQuery $query): EventFormOptions
    {
        return $this->repository->options();
    }
}
