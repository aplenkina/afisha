<?php

namespace App\Catalog\Application\UpdateEvent;

use App\Catalog\Infrastructure\Repository\CategoryRepositoryInterface;
use App\Catalog\Infrastructure\Repository\CityRepositoryInterface;
use App\Catalog\Infrastructure\Repository\EventRepositoryInterface;
use App\Catalog\Infrastructure\Repository\VenueRepositoryInterface;
use App\Shared\Application\FlusherInterface;
use App\Shared\Bus\Command\HandlerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class UpdateEventHandler implements HandlerInterface
{
    public function __construct(
        private EventRepositoryInterface $eventRepository,
        private CategoryRepositoryInterface $categoryRepository,
        private CityRepositoryInterface $cityRepository,
        private VenueRepositoryInterface $venueRepository,
        private FlusherInterface $flusher,
    ) {
    }

    public function __invoke(UpdateEventCommand $command): void
    {
        $event = $this->eventRepository->findById($command->id);

        $this->categoryRepository->findById($command->categoryId);
        $this->cityRepository->findById($command->cityId);

        $venueIds = [$command->venueId];
        foreach ($command->sessions as $session) {
            $venueIds[] = $session->venueId;
        }
        foreach (array_unique(array_filter($venueIds)) as $venueId) {
            $this->venueRepository->findById($venueId);
        }

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
        foreach ($command->sessions as $data) {
            $session = $data->id !== null ? $event->findSession($data->id) : null;

            if ($session === null) {
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
}
