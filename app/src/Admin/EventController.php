<?php

namespace App\Admin;

use App\Admin\Application\EventForm\EventFormOptionsQuery;
use App\Admin\Application\GetEvent\GetEventQuery;
use App\Admin\Application\GetEvent\GetEventResponse;
use App\Admin\Application\ListEvents\ListEventsResponse;
use App\Admin\Infrastructure\Http\EventFormCommandFactory;
use App\Admin\Infrastructure\Http\EventFormPresenter;
use App\Admin\Infrastructure\Http\EventListPresenter;
use App\Admin\Request\EventListRequest;
use App\Admin\Request\EventRequest;
use App\Admin\Security\EventVoter;
use App\Catalog\Application\ChangeEventStatus\ChangeEventStatusCommand;
use App\Catalog\Application\CreateEvent\CreateEventCommand;
use App\Catalog\Application\DeleteEvent\DeleteEventCommand;
use App\Catalog\Application\MergeDuplicateEvents\MergeDuplicateEvents;
use App\Catalog\Domain\EventNotFound;
use App\Catalog\Domain\EventStatus;
use App\Identity\Infrastructure\Security\AuthenticatedUser;
use App\Shared\Domain\DomainError;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AdminRoute('/event', name: 'event')]
#[IsGranted('ROLE_EDITOR')]
class EventController extends AbstractAdminController
{
    #[AdminRoute('/', name: 'index', options: ['methods' => 'GET'])]
    public function index(
        EventListPresenter $presenter,
        #[CurrentUser] AuthenticatedUser $user,
        #[MapQueryString(validationFailedStatusCode: Response::HTTP_BAD_REQUEST)]
        EventListRequest $filters = new EventListRequest(),
    ): Response {
        /** @var ListEventsResponse $result */
        $result = $this->dispatch($filters->toQuery($this->isGranted('ROLE_MODERATOR') ? null : $user->id));

        return $presenter->present($result, $filters);
    }

    #[AdminRoute('/new', name: 'new', options: ['methods' => 'GET'])]
    public function new(EventFormPresenter $presenter): Response
    {
        return $presenter->form($this->dispatch(new EventFormOptionsQuery()), null);
    }

    #[AdminRoute('/{id<\d+>}', name: 'details', options: ['methods' => 'GET'])]
    public function details(int $id, EventFormPresenter $presenter): Response
    {
        $result = $this->getEvent($id, $this->isGranted('ROLE_MODERATOR'));
        $this->denyAccessUnlessGranted(EventVoter::EDIT, $result->event);

        return $presenter->form($this->dispatch(new EventFormOptionsQuery()), $result->event, $result->duplicates, $result->comparisons);
    }

    #[AdminRoute('/save', name: 'save', options: ['methods' => 'POST'])]
    public function save(
        #[MapRequestPayload] EventRequest $request,
        #[CurrentUser] AuthenticatedUser $user,
        EventFormCommandFactory $commands,
        EventFormPresenter $presenter,
    ): Response {
        $id = $request->eventId();
        if (null !== $id) {
            $this->denyAccessUnlessGranted(EventVoter::EDIT, $this->getEvent($id)->event);
        }

        $command = $commands->create($request, $user->id);
        try {
            $result = $this->dispatch($command);
        } catch (DomainError $error) {
            return $presenter->failed($error, $id);
        }

        return $presenter->saved($command instanceof CreateEventCommand ? $result : $command->id);
    }

    #[AdminRoute('/{id<\d+>}/status', name: 'status', options: ['methods' => 'POST'])]
    #[IsGranted('ROLE_MODERATOR')]
    public function status(int $id, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('event-status-'.$id, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $status = EventStatus::tryFrom($request->request->getString('status'));

        if (null === $status) {
            throw $this->createNotFoundException();
        }

        try {
            $result = $this->dispatch(new ChangeEventStatusCommand($id, $status));
            if (EventStatus::Published === $status && EventStatus::Duplicate === $result) {
                $this->addFlash('success', 'Карточка помечена как дубль и скрыта с сайта.');
            }
        } catch (DomainError|\InvalidArgumentException $error) {
            return $this->fail($error, 'admin_event_index');
        }

        return $this->redirectToRoute('admin_event_index', array_filter([
            'status' => $request->query->getString('status'),
            'category' => $request->query->getString('category'),
            'q' => $request->query->getString('q'),
            'regex' => $request->query->getString('regex'),
            'page' => $request->query->getString('page'),
        ], static fn (string $value): bool => '' !== $value));
    }

    #[AdminRoute('/{id<\d+>}/merge', name: 'merge', options: ['methods' => 'POST'])]
    #[IsGranted('ROLE_MODERATOR')]
    public function merge(int $id, Request $request, MergeDuplicateEvents $merger): Response
    {
        if (!$this->isCsrfTokenValid('merge-event-'.$id, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $targetId = $request->request->getInt('target');
        try {
            $merger->merge($id, $targetId);
        } catch (\InvalidArgumentException|EventNotFound $error) {
            return $this->fail($error, 'admin_event_details', ['id' => $id]);
        }

        $this->addFlash('success', 'Карточка помечена как дубль и скрыта с сайта.');

        return $this->redirectToRoute('admin_event_details', ['id' => $targetId]);
    }

    #[AdminRoute('/{id<\d+>}', name: 'delete', options: ['methods' => 'DELETE'])]
    public function delete(int $id): Response
    {
        $this->denyAccessUnlessGranted(EventVoter::EDIT, $this->getEvent($id)->event);

        try {
            $this->dispatch(new DeleteEventCommand($id));
            $this->addFlash('success', 'Событие удалено');
        } catch (DomainError $error) {
            return $this->fail($error, 'admin_event_index');
        }

        return $this->redirectToRoute('admin_event_index');
    }

    private function getEvent(int $id, bool $includeModeration = false): GetEventResponse
    {
        try {
            /** @var GetEventResponse $result */
            $result = $this->dispatch(new GetEventQuery($id, $includeModeration));
        } catch (EventNotFound $exception) {
            throw $this->createNotFoundException($exception->getMessage(), $exception);
        }

        return $result;
    }
}
