<?php

declare(strict_types=1);

namespace App\Admin\Infrastructure\Http;

use App\Admin\Request\EventRequest;
use App\Catalog\Application\CreateEvent\CreateEventCommand;
use App\Catalog\Application\CreateEvent\CreateEventSession;
use App\Catalog\Application\UpdateEvent\UpdateEventCommand;
use App\Catalog\Application\UpdateEvent\UpdateEventSession;
use App\Catalog\Domain\SessionStatus;

final class EventFormCommandFactory
{
    public function create(EventRequest $request, int $actorId): CreateEventCommand|UpdateEventCommand
    {
        $id = $request->eventId();
        $fields = [
            'title' => $request->title,
            'categoryId' => (int) $request->categoryId,
            'cityId' => (int) $request->cityId,
            'venueId' => self::optionalInt($request->venueId),
            'description' => self::optionalText($request->description),
            'priceMin' => self::optionalPrice($request->priceMin),
            'priceMax' => self::optionalPrice($request->priceMax),
            'isFree' => '1' === $request->isFree,
            'ageLimit' => self::optionalInt($request->ageLimit),
            'imageUrl' => self::optionalText($request->imageUrl),
            'sourceUrl' => self::optionalText($request->sourceUrl),
            'ticketUrl' => self::optionalText($request->ticketUrl),
        ];
        $sessions = [];
        foreach ($request->sessions as $row) {
            $startsAt = $row->startTime();
            if (null === $startsAt) {
                continue;
            }

            $session = [
                'startsAt' => $startsAt,
                'endsAt' => $row->endTime(),
                'venueId' => self::optionalInt($row->venueId),
                'ticketUrl' => self::optionalText($row->ticketUrl),
            ];
            $sessions[] = null === $id ? new CreateEventSession(...$session) : new UpdateEventSession(
                ...$session,
                id: self::optionalInt($row->id),
                status: SessionStatus::tryFrom($row->status ?? '') ?? SessionStatus::Scheduled,
            );
        }

        return null === $id
            ? new CreateEventCommand(...$fields, createdBy: $actorId, sessions: $sessions)
            : new UpdateEventCommand(...$fields, id: $id, sessions: $sessions);
    }

    private static function optionalInt(?string $value): ?int
    {
        return null === $value || '' === $value ? null : (int) $value;
    }

    private static function optionalText(?string $value): ?string
    {
        $value = trim($value ?? '');

        return '' === $value ? null : $value;
    }

    private static function optionalPrice(?string $value): ?string
    {
        $value = self::optionalText($value);
        if (null === $value) {
            return null;
        }

        [$whole, $cents] = array_pad(explode('.', str_replace(',', '.', $value), 2), 2, '');

        return (int) $whole.'.'.str_pad($cents, 2, '0');
    }
}
