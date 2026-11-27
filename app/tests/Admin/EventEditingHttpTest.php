<?php

declare(strict_types=1);

namespace App\Tests\Admin;

use App\Admin\Application\EventForm\EventFormOptions;
use App\Admin\Application\EventForm\EventFormOptionsRepository;
use App\Catalog\Application\EventEditing\EventEditingRepository;
use App\Catalog\Application\EventEditing\EventReferences;
use App\Catalog\Domain\Event;
use App\Catalog\Domain\EventSession;
use App\Catalog\Domain\SessionStatus;
use App\Identity\Domain\UserRole;
use App\Identity\Infrastructure\Security\AuthenticatedUser;
use App\Shared\Application\FlusherInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Security\Core\User\UserProviderInterface;

final class EventEditingHttpTest extends WebTestCase
{
    public function testNewFormRendersCatalogChoices(): void
    {
        $client = $this->editor();
        $options = $this->createStub(EventFormOptionsRepository::class);
        $options->method('options')->willReturn(new EventFormOptions(
            [['id' => 2, 'name' => 'Минск']],
            [['id' => 3, 'name' => 'Сцена']],
            [['id' => 1, 'name' => 'Концерты']],
        ));
        self::getContainer()->set(EventFormOptionsRepository::class, $options);

        $client->request('GET', '/admin/event/new');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextSame('#cityId option[value="2"]', 'Минск');
        self::assertSelectorTextSame('#categoryId option[value="1"]', 'Концерты');
        self::assertSelectorTextSame('#venueId option[value="3"]', 'Сцена');
    }

    public function testSaveCreatesEventWithValidatedTypedFieldsAndSparseSessions(): void
    {
        $client = $this->editor();
        $references = $this->createMock(EventReferences::class);
        $references->expects(self::once())->method('check')->with(1, 2, [3, null]);
        self::getContainer()->set(EventReferences::class, $references);
        $events = $this->createMock(EventEditingRepository::class);
        $events->expects(self::once())->method('save')->willReturnCallback(static function (Event $event): void {
            self::assertSame(7, $event->getCreatedBy());
            self::assertSame('1.20', $event->getPriceMin());
            self::assertSame('2.00', $event->getPriceMax());
            self::assertSame(0, $event->getAgeLimit());
            self::assertCount(1, $event->getSessions());
            self::assertSame('2027-02-19T19:00:00+03:00', $event->getSessions()[0]->getStartsAt()->format('c'));
            self::assertSame(3, $event->getSessions()[0]->getVenueId());
            new \ReflectionProperty(Event::class, 'id')->setValue($event, 47);
        });
        self::getContainer()->set(EventEditingRepository::class, $events);
        self::getContainer()->set(FlusherInterface::class, $this->createStub(FlusherInterface::class));

        $client->request('POST', '/admin/event/save', self::payload([
            'id' => '',
            'venueId' => '3',
            'ageLimit' => '0',
            'priceMin' => '0001,2',
            'priceMax' => '2',
            'sessions' => [
                2 => ['id' => '', 'startsAt' => '', 'endsAt' => '', 'ticketUrl' => '', 'venueId' => ''],
                7 => ['startsAt' => '2027-02-19T19:00', 'endsAt' => '2027-02-19T21:00'],
            ],
        ]));

        self::assertResponseRedirects('/admin/event/47');
    }

    public function testExistingEventKeepsItsSlugAndUpdatesSessions(): void
    {
        $client = $this->editor();
        $event = self::event(7);
        $session = self::addSession($event, 17);
        self::addSession($event, 18);
        $events = $this->createStub(EventEditingRepository::class);
        $events->method('findById')->willReturn($event);
        self::getContainer()->set(EventEditingRepository::class, $events);
        self::getContainer()->set(EventReferences::class, $this->createStub(EventReferences::class));
        $flusher = $this->createMock(FlusherInterface::class);
        $flusher->expects(self::once())->method('flush');
        self::getContainer()->set(FlusherInterface::class, $flusher);

        $client->request('POST', '/admin/event/save', self::payload([
            'id' => '47',
            'sessions' => [
                ['id' => '17', 'startsAt' => '2027-02-20T19:00', 'status' => 'sold_out'],
                ['startsAt' => '2027-02-21T19:30', 'status' => 'scheduled'],
            ],
        ]));

        self::assertResponseRedirects('/admin/event/47');
        self::assertSame('existing-event', $event->getSlug());
        self::assertSame('Джазовый вечер', $event->getTitle());
        self::assertCount(2, $event->getSessions());
        self::assertSame($session, $event->findSession(17));
        self::assertSame(SessionStatus::SoldOut, $session->getStatus());
        self::assertNotContains(18, array_map(static fn (EventSession $item): ?int => new \ReflectionProperty(EventSession::class, 'id')->getValue($item), $event->getSessions()));
    }

    public function testEditorCannotSaveOtherOwnersEvent(): void
    {
        $client = $this->editor();
        $events = $this->createMock(EventEditingRepository::class);
        $events->expects(self::once())->method('findById')->with(47)->willReturn(self::event(8));
        self::getContainer()->set(EventEditingRepository::class, $events);

        $client->request('POST', '/admin/event/save', self::payload(['id' => '47']));

        self::assertResponseStatusCodeSame(403);
    }

    #[DataProvider('badForms')]
    public function testBadInputsAreRejectedBeforeDispatch(array $fields): void
    {
        $client = $this->editor();
        $references = $this->createMock(EventReferences::class);
        $references->expects(self::never())->method('check');
        self::getContainer()->set(EventReferences::class, $references);

        $client->request('POST', '/admin/event/save', self::payload($fields));

        self::assertResponseStatusCodeSame(422);
    }

    public static function badForms(): iterable
    {
        yield 'negative ID' => [['cityId' => '-2']];
        yield 'invalid price' => [['priceMin' => 'договорная']];
        yield 'scope of checkbox' => [['isFree' => '0']];
        yield 'nonexistent date' => [['sessions' => [['startsAt' => '2027-02-30T19:00']]]];
        yield 'early end' => [['sessions' => [['startsAt' => '2027-02-19T19:00', 'endsAt' => '2027-02-19T18:00']]]];
        yield 'incomplete row' => [['sessions' => [['ticketUrl' => 'https://example.com/tickets']]]];
        yield 'unknown status' => [['sessions' => [['startsAt' => '2027-02-19T19:00', 'status' => 'maybe']]]];
    }

    private function editor(): KernelBrowser
    {
        $client = self::createClient();
        $provider = $this->createStub(UserProviderInterface::class);
        $provider->method('supportsClass')->willReturn(true);
        $provider->method('refreshUser')->willReturnArgument(0);
        self::getContainer()->set('security.user.provider.concrete.app_users', $provider);
        $client->loginUser(new AuthenticatedUser(7, 'editor@example.test', UserRole::Editor, 'hash-editor', true), 'identity');

        return $client;
    }

    private static function payload(array $fields): array
    {
        return $fields + ['title' => 'Джазовый вечер', 'categoryId' => '1', 'cityId' => '2'];
    }

    private static function event(int $ownerId): Event
    {
        $event = Event::create('Старое название', 'existing-event', 1, 2, createdBy: $ownerId);
        new \ReflectionProperty(Event::class, 'id')->setValue($event, 47);

        return $event;
    }

    private static function addSession(Event $event, int $id): EventSession
    {
        $session = $event->addSession(new \DateTimeImmutable('2027-02-19T19:00:00+03:00'));
        new \ReflectionProperty(EventSession::class, 'id')->setValue($session, $id);

        return $session;
    }
}
