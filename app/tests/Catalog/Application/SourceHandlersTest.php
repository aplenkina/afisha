<?php

declare(strict_types=1);

namespace App\Tests\Catalog\Application;

use App\Catalog\Application\ChangeSourceAvailability\ChangeSourceAvailabilityCommand;
use App\Catalog\Application\ChangeSourceAvailability\ChangeSourceAvailabilityHandler;
use App\Catalog\Application\CreateSource\CreateSourceCommand;
use App\Catalog\Application\CreateSource\CreateSourceHandler;
use App\Catalog\Application\DeleteSource\DeleteSourceCommand;
use App\Catalog\Application\DeleteSource\DeleteSourceHandler;
use App\Catalog\Application\UpdateSource\UpdateSourceCommand;
use App\Catalog\Application\UpdateSource\UpdateSourceHandler;
use App\Catalog\Domain\City;
use App\Catalog\Domain\Source;
use App\Catalog\Domain\SourceInUse;
use App\Catalog\Domain\SourceNotFound;
use App\Catalog\Domain\SourceSlugTaken;
use App\Catalog\Infrastructure\Repository\CityRepositoryInterface;
use App\Catalog\Infrastructure\Repository\EventRepositoryInterface;
use App\Shared\Application\FlusherInterface;
use App\Shared\Infrastructure\Slug\AsciiSlugGenerator;
use App\Tests\Catalog\Support\InMemorySourceRepository;
use PHPUnit\Framework\TestCase;

final class SourceHandlersTest extends TestCase
{
    private InMemorySourceRepository $sources;
    private CityRepositoryInterface $cities;
    private FlusherInterface $flusher;

    protected function setUp(): void
    {
        $this->sources = new InMemorySourceRepository();
        $this->cities = $this->createStub(CityRepositoryInterface::class);
        $this->cities->method('findById')->willReturn($this->createStub(City::class));
        $this->flusher = $this->createStub(FlusherInterface::class);
    }

    public function testCreateGeneratesSlugFromNameWhenNotGiven(): void
    {
        $this->flusher = $this->createMock(FlusherInterface::class);
        $this->flusher->expects(self::once())->method('flush');

        $id = $this->create(new CreateSourceCommand(5, 'Билеты Про', null, 'https://www.ticketpro.by'));

        $source = $this->sources->findById($id);
        self::assertSame('bilety-pro', $source->getSlug());
        self::assertSame('https://www.ticketpro.by', $source->getExternalSource());
        self::assertFalse($source->isAvailable(), 'новый источник выключен, пока его не включат');
    }

    public function testCreateAcceptsLongSourceUrl(): void
    {
        $url = 'https://example.by/'.str_repeat('a', 400);

        $id = $this->create(new CreateSourceCommand(5, 'Длинный', 'long', $url));

        self::assertSame($url, $this->sources->findById($id)->getExternalSource());
    }

    public function testCreateWithSameCityAndNameUpdatesInsteadOfDuplicating(): void
    {
        $first = $this->create(new CreateSourceCommand(5, 'Ticketpro', 'ticketpro', 'https://a.by'));
        $second = $this->create(new CreateSourceCommand(5, 'Ticketpro', 'ticketpro', 'https://b.by'));

        self::assertSame($first, $second);
        self::assertSame(1, $this->sources->count());
        self::assertSame('https://b.by', $this->sources->findById($first)->getExternalSource());
    }

    public function testCreateRejectsSlugOfAnotherSource(): void
    {
        $this->create(new CreateSourceCommand(5, 'Первый', 'shared', 'https://a.by'));

        $this->expectException(SourceSlugTaken::class);
        $this->create(new CreateSourceCommand(5, 'Второй', 'shared', 'https://b.by'));
    }

    public function testUpdateChangesFields(): void
    {
        $source = $this->sources->add(Source::create(5, 'Старое', 'old', 'https://old.by'));

        $this->update(new UpdateSourceCommand($source->getId(), 7, 'Новое', 'new-slug', 'https://new.by'));

        $updated = $this->sources->findById($source->getId());
        self::assertSame(7, $updated->getCityId());
        self::assertSame('Новое', $updated->getName());
        self::assertSame('new-slug', $updated->getSlug());
        self::assertSame('https://new.by', $updated->getExternalSource());
    }

    public function testUpdateKeepsSlugWhenEmpty(): void
    {
        $source = $this->sources->add(Source::create(5, 'Ticketpro', 'ticketpro', 'https://a.by'));

        foreach ([null, ''] as $emptySlug) {
            $this->update(new UpdateSourceCommand($source->getId(), 5, 'Ticketpro 2', $emptySlug, 'https://a.by'));
            self::assertSame('ticketpro', $this->sources->findById($source->getId())->getSlug());
        }
    }

    public function testUpdateMayKeepOwnSlug(): void
    {
        $source = $this->sources->add(Source::create(5, 'Ticketpro', 'ticketpro', 'https://a.by'));

        $this->update(new UpdateSourceCommand($source->getId(), 5, 'Ticketpro', 'ticketpro', 'https://b.by'));

        self::assertSame('https://b.by', $this->sources->findById($source->getId())->getExternalSource());
    }

    public function testUpdateRejectsSlugOfAnotherSource(): void
    {
        $this->sources->add(Source::create(5, 'Первый', 'first', 'https://a.by'));
        $second = $this->sources->add(Source::create(5, 'Второй', 'second', 'https://b.by'));

        $this->expectException(SourceSlugTaken::class);
        $this->update(new UpdateSourceCommand($second->getId(), 5, 'Второй', 'first', 'https://b.by'));
    }

    public function testUpdateUnknownSource(): void
    {
        $this->expectException(SourceNotFound::class);
        $this->update(new UpdateSourceCommand(999, 5, 'X', 'x', 'https://x.by'));
    }

    public function testDeleteRemovesSourceWithoutEvents(): void
    {
        $source = $this->sources->add(Source::create(5, 'Пустой', 'empty', 'https://a.by'));
        $events = $this->createStub(EventRepositoryInterface::class);
        $events->method('existsBySource')->willReturn(false);
        $this->flusher = $this->createMock(FlusherInterface::class);
        $this->flusher->expects(self::once())->method('flush');

        (new DeleteSourceHandler($this->sources, $events, $this->flusher))(new DeleteSourceCommand($source->getId()));

        self::assertSame(0, $this->sources->count());
    }

    public function testDeleteIsRefusedWhenSourceHasEvents(): void
    {
        $this->sources->add(Source::create(5, 'Другой', 'other', 'https://o.by'));
        $source = $this->sources->add(Source::create(5, 'Ticketpro', 'ticketpro', 'https://a.by'));
        $events = $this->createStub(EventRepositoryInterface::class);
        $events->method('existsBySource')->willReturnCallback(
            static fn (int $id): bool => $id === $source->getId(),
        );
        $this->flusher = $this->createMock(FlusherInterface::class);
        $this->flusher->expects(self::never())->method('flush');

        try {
            (new DeleteSourceHandler($this->sources, $events, $this->flusher))(new DeleteSourceCommand($source->getId()));
            self::fail('Ожидали SourceInUse');
        } catch (SourceInUse) {
            self::assertSame(2, $this->sources->count(), 'источник с событиями не должен удаляться');
        }
    }

    public function testDeleteUnknownSource(): void
    {
        $events = $this->createStub(EventRepositoryInterface::class);

        $this->expectException(SourceNotFound::class);
        (new DeleteSourceHandler($this->sources, $events, $this->flusher))(new DeleteSourceCommand(999));
    }

    public function testConfigIsStoredOnCreateAndReplacedOnUpdate(): void
    {
        $config = ['pages' => ['/'], 'categories' => ['koncert' => 'concert']];
        $id = $this->create(new CreateSourceCommand(5, 'Сайт', 'site', 'https://site.by', $config));
        self::assertSame($config, $this->sources->findById($id)->getConfig());

        $newConfig = ['pages' => ['/afisha/'], 'categories' => ['teatr' => 'theatre'], 'city_fallback' => true];
        $this->update(new UpdateSourceCommand($id, 5, 'Сайт', 'site', 'https://site.by', $newConfig));
        self::assertSame($newConfig, $this->sources->findById($id)->getConfig());

        $this->update(new UpdateSourceCommand($id, 5, 'Сайт', 'site', 'https://site.by', null));
        self::assertNull($this->sources->findById($id)->getConfig(), 'пустая форма сбрасывает настройки');
    }

    public function testAvailabilityCanBeSwitchedBothWays(): void
    {
        $source = $this->sources->add(Source::create(5, 'Ticketpro', 'ticketpro', 'https://a.by'));
        $handler = new ChangeSourceAvailabilityHandler($this->sources, $this->flusher);

        $handler(new ChangeSourceAvailabilityCommand($source->getId(), true));
        self::assertTrue($this->sources->findById($source->getId())->isAvailable());

        $handler(new ChangeSourceAvailabilityCommand($source->getId(), false));
        self::assertFalse($this->sources->findById($source->getId())->isAvailable());
    }

    public function testAvailabilityOfUnknownSource(): void
    {
        $this->expectException(SourceNotFound::class);
        (new ChangeSourceAvailabilityHandler($this->sources, $this->flusher))(new ChangeSourceAvailabilityCommand(999, true));
    }

    private function create(CreateSourceCommand $command): int
    {
        return (new CreateSourceHandler($this->sources, $this->cities, $this->flusher, new AsciiSlugGenerator()))($command);
    }

    private function update(UpdateSourceCommand $command): void
    {
        (new UpdateSourceHandler($this->sources, $this->cities, $this->flusher, new AsciiSlugGenerator()))($command);
    }
}
