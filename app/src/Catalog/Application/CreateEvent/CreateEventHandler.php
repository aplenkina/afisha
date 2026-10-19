<?php

namespace App\Catalog\Application\CreateEvent;

use App\Catalog\Domain\Event;
use App\Catalog\Domain\EventAlreadyImported;
use App\Catalog\Infrastructure\Repository\CategoryRepositoryInterface;
use App\Catalog\Infrastructure\Repository\CityRepositoryInterface;
use App\Catalog\Infrastructure\Repository\EventRepositoryInterface;
use App\Catalog\Infrastructure\Repository\VenueRepositoryInterface;
use App\Shared\Application\FlusherInterface;
use App\Shared\Application\SlugGeneratorInterface;
use App\Shared\Bus\Command\HandlerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class CreateEventHandler implements HandlerInterface
{
    private const string DEFAULT_SLUG = 'event';

    public function __construct(
        private EventRepositoryInterface $eventRepository,
        private CategoryRepositoryInterface $categoryRepository,
        private CityRepositoryInterface $cityRepository,
        private VenueRepositoryInterface $venueRepository,
        private SlugGeneratorInterface $slugGenerator,
        private FlusherInterface $flusher,
    ) {
    }

    public function __invoke(CreateEventCommand $command): int
    {
        $this->categoryRepository->findById($command->categoryId);
        $this->cityRepository->findById($command->cityId);

        $venueIds = [$command->venueId];
        foreach ($command->sessions as $session) {
            $venueIds[] = $session->venueId;
        }
        foreach (array_unique(array_filter($venueIds)) as $venueId) {
            $this->venueRepository->findById($venueId);
        }

        if (
            null !== $command->sourceId
            && null !== $command->externalId
            && $this->eventRepository->existsBySourceExternalId($command->sourceId, $command->externalId)
        ) {
            throw new EventAlreadyImported($command->sourceId, $command->externalId);
        }

        $event = Event::create(
            title: $command->title,
            slug: $this->uniqueSlug($command->title),
            categoryId: $command->categoryId,
            cityId: $command->cityId,
            venueId: $command->venueId,
            description: $command->description,
            priceMin: $command->priceMin,
            priceMax: $command->priceMax,
            isFree: $command->isFree,
            ageLimit: $command->ageLimit,
            imageUrl: $command->imageUrl,
            sourceUrl: $command->sourceUrl,
            ticketUrl: $command->ticketUrl,
            sourceId: $command->sourceId,
            externalId: $command->externalId,
            createdBy: $command->createdBy,
        );

        foreach ($command->sessions as $session) {
            $event->addSession(
                startsAt: $session->startsAt,
                endsAt: $session->endsAt,
                venueId: $session->venueId ?? $command->venueId,
                ticketUrl: $session->ticketUrl,
            );
        }

        $this->eventRepository->save($event);
        $this->flusher->flush();

        return $event->getId();
    }

    private function uniqueSlug(string $title): string
    {
        $base = $this->slugGenerator->generate($title);
        $base = '' !== $base ? $base : self::DEFAULT_SLUG;

        $slug = $base;
        for ($suffix = 2; $this->eventRepository->existsBySlug($slug); ++$suffix) {
            $slug = sprintf('%s-%d', $base, $suffix);
        }

        return $slug;
    }
}
