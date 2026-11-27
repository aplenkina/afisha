<?php

declare(strict_types=1);

namespace App\Admin\Application\GetEvent;

use App\Catalog\Application\EventEditing\EventEditingRepository;
use App\Catalog\Application\EventEditing\ImportedEventComparisons;
use App\Catalog\Domain\EventStatus;
use App\Shared\Bus\Query\QueryHandlerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetEventHandler implements QueryHandlerInterface
{
    public function __construct(private EventEditingRepository $events, private ?ImportedEventComparisons $imports = null)
    {
    }

    public function __invoke(GetEventQuery $query): GetEventResponse
    {
        $event = $this->events->findById($query->id);
        if (!$query->includeModeration) {
            return new GetEventResponse($event);
        }

        $duplicates = \in_array($event->getStatus(), [EventStatus::Draft, EventStatus::Published], true)
            ? $this->events->findDuplicateCandidates($query->id)
            : [];
        $comparisons = $this->imports?->forEvent($event) ?? [];

        return new GetEventResponse($event, $duplicates, $comparisons);
    }
}
