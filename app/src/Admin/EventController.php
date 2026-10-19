<?php

namespace App\Admin;

use App\Admin\Application\GetEvent\GetEventQuery;
use App\Admin\Application\GetEvent\GetEventResponse;
use App\Admin\Application\ListCities\ListCitiesQuery;
use App\Admin\Application\ListCities\ListCitiesResponse;
use App\Admin\Application\ListEvents\ListEventsQuery;
use App\Admin\Application\ListEvents\ListEventsResponse;
use App\Admin\Application\ListVenues\ListVenuesQuery;
use App\Admin\Application\ListVenues\ListVenuesResponse;
use App\Admin\Request\EventRequest;
use App\Admin\Security\EventVoter;
use App\Catalog\Application\ChangeEventStatus\ChangeEventStatusCommand;
use App\Catalog\Application\CreateEvent\CreateEventCommand;
use App\Catalog\Application\CreateEvent\CreateEventSession;
use App\Catalog\Application\DeleteEvent\DeleteEventCommand;
use App\Catalog\Application\ShowCatalog\CatalogShowQuery;
use App\Catalog\Application\ShowCatalog\CatalogShowResponse;
use App\Catalog\Application\UpdateEvent\UpdateEventCommand;
use App\Catalog\Application\UpdateEvent\UpdateEventSession;
use App\Catalog\Domain\Event;
use App\Catalog\Domain\EventNotFound;
use App\Catalog\Domain\EventStatus;
use App\Catalog\Domain\SessionStatus;
use App\Identity\Domain\User;
use App\Shared\Domain\DomainError;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AdminRoute('/event', name: 'event')]
#[IsGranted('ROLE_EDITOR')]
class EventController extends AbstractAdminController
{
    #[AdminRoute('/', name: 'index', options: ['methods' => 'GET'])]
    public function index(Request $request): Response
    {
        $status = EventStatus::tryFrom($request->query->getString('status'));
        $categoryId = $this->toInt($request->query->getString('category'));

        $createdBy = $this->isGranted('ROLE_MODERATOR') ? null : $this->currentUserId();

        /** @var ListEventsResponse $result */
        $result = $this->dispatch(new ListEventsQuery($status, $createdBy, $categoryId));

        return $this->render('/admin/event/index.html.twig', [
            'events' => $result->events,
            'status' => $status,
            'categoryId' => $categoryId,
            'statuses' => EventStatus::cases(),
            'cities' => $this->namesById($this->cities()),
            'categories' => $this->categoryNames(),
        ]);
    }

    #[AdminRoute('/new', name: 'new', options: ['methods' => 'GET'])]
    public function new(): Response
    {
        return $this->render('/admin/event/details.html.twig', ['event' => null] + $this->formOptions());
    }

    #[AdminRoute('/{id<\d+>}', name: 'details', options: ['methods' => 'GET'])]
    public function details(int $id): Response
    {
        try {
            /** @var GetEventResponse $result */
            $result = $this->dispatch(new GetEventQuery($id));
        } catch (EventNotFound $exception) {
            throw $this->createNotFoundException($exception->getMessage(), $exception);
        }

        $this->denyAccessUnlessGranted(EventVoter::EDIT, $result->event);

        return $this->render('/admin/event/details.html.twig', ['event' => $result->event] + $this->formOptions());
    }

    #[AdminRoute('/save', name: 'save', options: ['methods' => 'POST'])]
    public function save(#[MapRequestPayload] EventRequest $request): Response
    {
        $id = $this->toInt($request->id);

        if (null !== $id) {
            $this->denyAccessUnlessGranted(EventVoter::EDIT, $this->loadEvent($id));
        }

        try {
            if (null === $id) {
                $id = $this->dispatch(new CreateEventCommand(
                    title: $request->title,
                    categoryId: (int) $request->categoryId,
                    cityId: (int) $request->cityId,
                    venueId: $this->toInt($request->venueId),
                    description: $this->toString($request->description),
                    priceMin: $this->toDecimal($request->priceMin),
                    priceMax: $this->toDecimal($request->priceMax),
                    isFree: null !== $request->isFree,
                    ageLimit: $this->toInt($request->ageLimit),
                    imageUrl: $this->toString($request->imageUrl),
                    sourceUrl: $this->toString($request->sourceUrl),
                    ticketUrl: $this->toString($request->ticketUrl),
                    createdBy: $this->currentUserId(),
                    sessions: $this->createSessions($request),
                ));
            } else {
                $this->dispatch(new UpdateEventCommand(
                    id: $id,
                    title: $request->title,
                    categoryId: (int) $request->categoryId,
                    cityId: (int) $request->cityId,
                    venueId: $this->toInt($request->venueId),
                    description: $this->toString($request->description),
                    priceMin: $this->toDecimal($request->priceMin),
                    priceMax: $this->toDecimal($request->priceMax),
                    isFree: null !== $request->isFree,
                    ageLimit: $this->toInt($request->ageLimit),
                    imageUrl: $this->toString($request->imageUrl),
                    sourceUrl: $this->toString($request->sourceUrl),
                    ticketUrl: $this->toString($request->ticketUrl),
                    sessions: $this->updateSessions($request),
                ));
            }
        } catch (DomainError $error) {
            return $this->fail($error, null !== $id ? 'admin_event_details' : 'admin_event_new', null !== $id ? ['id' => $id] : []);
        } catch (\Throwable $error) {
            return $this->fail($error, $request->id ? 'admin_event_details' : 'admin_event_new', $request->id ? ['id' => $request->id] : []);
        }

        $this->addFlash('success', 'Событие сохранено');

        return $this->redirectToRoute('admin_event_details', ['id' => $id]);
    }

    #[AdminRoute('/{id<\d+>}/status', name: 'status', options: ['methods' => 'POST'])]
    #[IsGranted('ROLE_MODERATOR')]
    public function status(int $id, Request $request): Response
    {
        $status = EventStatus::tryFrom($request->request->getString('status'));

        if (null === $status) {
            throw $this->createNotFoundException();
        }

        try {
            $this->dispatch(new ChangeEventStatusCommand($id, $status));
        } catch (DomainError $error) {
            return $this->fail($error, 'admin_event_index');
        }

        return $this->redirectToRoute('admin_event_index', array_filter([
            'status' => $request->query->getString('status'),
            'category' => $request->query->getString('category'),
        ], static fn (string $value): bool => '' !== $value));
    }

    #[AdminRoute('/{id<\d+>}', name: 'delete', options: ['methods' => 'DELETE'])]
    public function delete(int $id): Response
    {
        $this->denyAccessUnlessGranted(EventVoter::EDIT, $this->loadEvent($id));

        try {
            $this->dispatch(new DeleteEventCommand($id));
            $this->addFlash('success', 'Событие удалено');
        } catch (DomainError $error) {
            return $this->fail($error, 'admin_event_index');
        }

        return $this->redirectToRoute('admin_event_index');
    }

    private function loadEvent(int $id): Event
    {
        try {
            /** @var GetEventResponse $result */
            $result = $this->dispatch(new GetEventQuery($id));
        } catch (EventNotFound $exception) {
            throw $this->createNotFoundException($exception->getMessage(), $exception);
        }

        return $result->event;
    }

    private function currentUserId(): ?int
    {
        $user = $this->getUser();

        return $user instanceof User ? $user->getId() : null;
    }

    /**
     * @return list<CreateEventSession>
     */
    private function createSessions(EventRequest $request): array
    {
        $sessions = [];
        foreach ($request->sessions as $row) {
            $startsAt = $this->toDateTime($row->startsAt);
            if (null === $startsAt) {
                continue;
            }

            $sessions[] = new CreateEventSession(
                startsAt: $startsAt,
                endsAt: $this->toDateTime($row->endsAt),
                venueId: $this->toInt($row->venueId),
                ticketUrl: $this->toString($row->ticketUrl),
            );
        }

        return $sessions;
    }

    /**
     * @return list<UpdateEventSession>
     */
    private function updateSessions(EventRequest $request): array
    {
        $sessions = [];
        foreach ($request->sessions as $row) {
            $startsAt = $this->toDateTime($row->startsAt);
            if (null === $startsAt) {
                continue;
            }

            $sessions[] = new UpdateEventSession(
                startsAt: $startsAt,
                id: $this->toInt($row->id),
                endsAt: $this->toDateTime($row->endsAt),
                venueId: $this->toInt($row->venueId),
                ticketUrl: $this->toString($row->ticketUrl),
                status: SessionStatus::tryFrom((string) $row->status) ?? SessionStatus::Scheduled,
            );
        }

        return $sessions;
    }

    private function formOptions(): array
    {
        /** @var ListVenuesResponse $venues */
        $venues = $this->dispatch(new ListVenuesQuery());

        return [
            'cities' => $this->cities(),
            'venues' => $venues->venues,
            'categories' => $this->categories(),
            'sessionStatuses' => SessionStatus::cases(),
        ];
    }

    private function cities(): array
    {
        /** @var ListCitiesResponse $result */
        $result = $this->dispatch(new ListCitiesQuery());

        return $result->cities;
    }

    private function categories(): array
    {
        /** @var CatalogShowResponse $result */
        $result = $this->dispatch(new CatalogShowQuery());

        return $result->categories;
    }

    private function categoryNames(): array
    {
        return $this->namesById($this->categories());
    }

    private function namesById(array $items): array
    {
        $names = [];
        foreach ($items as $item) {
            $names[$item->getId()] = $item->getName();
        }

        return $names;
    }
}
