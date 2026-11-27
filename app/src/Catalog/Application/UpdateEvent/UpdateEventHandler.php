<?php

declare(strict_types=1);

namespace App\Catalog\Application\UpdateEvent;

use App\Catalog\Application\EventEditing\EventEditingRepository;
use App\Catalog\Application\EventEditing\EventReferences;
use App\Catalog\Domain\Event;
use App\Catalog\Domain\EventSession;
use App\Catalog\Domain\InvalidEventSession;
use App\Shared\Application\FlusherInterface;
use App\Shared\Bus\Command\HandlerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class UpdateEventHandler implements HandlerInterface
{
    public function __construct(
        private EventEditingRepository $eventRepository,
        private EventReferences $references,
        private FlusherInterface $flusher,
    ) {
    }

    public function __invoke(UpdateEventCommand $command): void
    {
        $event = $this->eventRepository->findById($command->id);

        $this->references->check($command->categoryId, $command->cityId, [
            $command->venueId,
            ...array_map(static fn (UpdateEventSession $session): ?int => $session->venueId, $command->sessions),
        ]);

        $sessions = $this->prepareSessions($event, $command->sessions);

        $event->update(
            title: $command->title,
            slug: $event->getSlug(),
            description: $command->description,
            categoryId: $command->categoryId,
            cityId: $command->cityId,
            venueId: $command->venueId,
            priceMin: $command->priceMin,
            priceMax: $command->priceMax,
            isFree: $command->isFree,
            ageLimit: $command->ageLimit,
            imageUrl: $command->imageUrl,
            sourceUrl: $command->sourceUrl,
            ticketUrl: $command->ticketUrl,
        );

        $keptSessions = [];
        foreach ($sessions as [$data, $session]) {
            if (null === $session) {
                $session = $event->addSession(
                    startsAt: $data->startsAt,
                    endsAt: $data->endsAt,
                    venueId: $data->venueId,
                    ticketUrl: $data->ticketUrl,
                );
            } else {
                $session->reschedule($data->startsAt, $data->endsAt);
                $session->changeVenue($data->venueId);
                $session->changeTicketUrl($data->ticketUrl);
            }

            $session->changeStatus($data->status);
            $keptSessions[] = $session;
        }

        foreach ($event->getSessions() as $session) {
            if (!in_array($session, $keptSessions, true)) {
                $event->removeSession($session);
            }
        }

        $this->flusher->flush();
    }

    /**
     * @param list<UpdateEventSession> $submitted
     *
     * @return list<array{UpdateEventSession, ?EventSession}>
     */
    private function prepareSessions(Event $event, array $submitted): array
    {
        $sessions = [];
        $seen = [];
        foreach ($submitted as $data) {
            EventSession::assertChronology($data->startsAt, $data->endsAt);
            $session = null;
            if (null !== $data->id) {
                $session = $event->findSession($data->id);
                if (null === $session || isset($seen[$data->id])) {
                    throw new InvalidEventSession('Сеанс отсутствует в событии или указан несколько раз. Обновите карточку.');
                }
                $seen[$data->id] = true;
            }

            $sessions[] = [$data, $session];
        }

        return $sessions;
    }
}
