<?php

declare(strict_types=1);

namespace App\Tests\Admin;

use App\Admin\Application\ListEvents\EventListItem;
use App\Admin\Application\ListEvents\EventListRepository;
use App\Admin\Application\ListEvents\ListEventsQuery;
use App\Catalog\Domain\EventStatus;
use App\Identity\Domain\UserRole;
use App\Identity\Infrastructure\Security\AuthenticatedUser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Security\Core\User\UserProviderInterface;

final class EventListHttpTest extends WebTestCase
{
    public function testEditorSearchIsScopedToTheirEventsAndRetainsFiltersOnPagination(): void
    {
        $client = self::createClient();
        $this->setUserProvider();
        $client->loginUser(new AuthenticatedUser(7, 'editor@example.test', UserRole::Editor, 'hash-editor', true), 'identity');

        $event = new EventListItem(
            47,
            'Саксофон — король джаза',
            'jazz',
            EventStatus::Draft,
            'Минск',
            'Концерты',
            2,
            new \DateTimeImmutable('2026-11-26 16:00:00Z'),
        );
        $repository = $this->createMock(EventListRepository::class);
        $repository->method('categoryNames')->willReturn([11 => 'Концерты']);
        $repository->expects(self::once())->method('find')->with(
            self::callback(static fn (ListEventsQuery $query): bool => 7 === $query->createdBy
                && 11 === $query->categoryId
                && EventStatus::Draft === $query->status
                && 'саксофон-король джаза' === $query->search),
            51,
            50,
        )->willReturn(array_fill(0, 51, $event));
        self::getContainer()->set(EventListRepository::class, $repository);

        $page = $client->request('GET', '/admin/event?'.http_build_query([
            'q' => 'саксофон-король джаза', 'status' => 'draft', 'category' => '11', 'page' => '2',
        ]));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('a[href="/admin/event/47"]', 'Саксофон — король джаза');
        self::assertSelectorTextContains('.admin-list__meta', 'сеансов: 2, ближайший 26.11.2026 19:00');
        self::assertCount(50, $page->filter('.admin-list__item'));
        parse_str((string) parse_url($page->filter('a[rel="next"]')->attr('href'), \PHP_URL_QUERY), $next);
        self::assertSame([
            'status' => 'draft', 'category' => '11', 'q' => 'саксофон-король джаза', 'page' => '3',
        ], $next);
    }

    public function testInvalidPageDoesNotQueryRepository(): void
    {
        $client = self::createClient();
        $this->setUserProvider();
        $client->loginUser(new AuthenticatedUser(2, 'moderator@example.test', UserRole::Moderator, 'hash-moderator', true), 'identity');
        $repository = $this->createMock(EventListRepository::class);
        $repository->expects(self::never())->method('find');
        self::getContainer()->set(EventListRepository::class, $repository);

        $client->request('GET', '/admin/event?page=0');

        self::assertResponseStatusCodeSame(400);
    }

    public function testModeratorCanSearchAcrossOwnersUsingExistingRegexMode(): void
    {
        $client = self::createClient();
        $this->setUserProvider();
        $client->loginUser(new AuthenticatedUser(2, 'moderator@example.test', UserRole::Moderator, 'hash-moderator', true), 'identity');

        $repository = $this->createMock(EventListRepository::class);
        $repository->expects(self::once())->method('find')->with(
            self::callback(static fn (ListEventsQuery $query): bool => null === $query->createdBy && $query->usesRegex()),
            200,
            0,
        )->willReturn([
            new EventListItem(47, 'Саксофон — король джаза', 'jazz', EventStatus::Draft, 'Минск', 'Концерты', 0, null),
            new EventListItem(46, 'Концерт', 'concert', EventStatus::Draft, 'Минск', 'Концерты', 0, null),
        ]);
        self::getContainer()->set(EventListRepository::class, $repository);

        $client->request('GET', '/admin/event?'.http_build_query(['q' => '^Саксофон', 'regex' => '1']));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.admin-list', 'Саксофон — король джаза');
        self::assertSelectorNotExists('a[href="/admin/event/46"]');
    }

    public function testLegacyFilterMenuOpensTheEventFilters(): void
    {
        $client = self::createClient();
        $this->setUserProvider();
        $client->loginUser(new AuthenticatedUser(2, 'admin@example.test', UserRole::Admin, 'hash-admin', true), 'identity');

        $client->request('GET', '/admin/filter');

        self::assertResponseRedirects('/admin/event');
    }

    private function setUserProvider(): void
    {
        $provider = $this->createStub(UserProviderInterface::class);
        $provider->method('supportsClass')->willReturn(true);
        $provider->method('refreshUser')->willReturnArgument(0);
        self::getContainer()->set('security.user.provider.concrete.app_users', $provider);
    }
}
