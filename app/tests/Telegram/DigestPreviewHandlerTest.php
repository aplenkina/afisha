<?php

declare(strict_types=1);

namespace App\Tests\Telegram;

use App\Telegram\Application\Digest;
use App\Telegram\Application\DigestPreviewHandler;
use App\Telegram\Application\PreviewDigest;
use App\Telegram\Application\TelegramCatalog;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class DigestPreviewHandlerTest extends TestCase
{
    #[DataProvider('horizons')]
    public function testUsesMinskTimeAndKeepsFilters(int $days, string $end): void
    {
        $query = new PreviewDigest('minsk', $days, true, 'concerts');
        $catalog = $this->createMock(TelegramCatalog::class);
        $catalog->expects(self::once())->method('digest')->with(
            self::identicalTo($query),
            self::callback(static fn (\DateTimeImmutable $from): bool => '2026-11-26 01:30 Europe/Minsk' === $from->format('Y-m-d H:i e')),
            self::callback(static fn (\DateTimeImmutable $until): bool => $until->format('Y-m-d H:i e') === $end.' 01:30 Europe/Minsk'),
        )->willReturnCallback(
            static fn (PreviewDigest $query, \DateTimeImmutable $from, \DateTimeImmutable $until): Digest => new Digest(
                $query->citySlug, 'Минск', $query->categoryAlias, $query->days, $query->onlyFree, $from, $until, [],
            ),
        );
        $handler = new DigestPreviewHandler($catalog, new MockClock('2026-11-25 22:30 UTC'));

        $digest = $handler($query);

        self::assertSame($days, $digest->days);
        self::assertSame('concerts', $digest->categoryAlias);
        self::assertTrue($digest->onlyFree);
    }

    public static function horizons(): iterable
    {
        yield [7, '2026-12-03'];
        yield [14, '2026-12-10'];
        yield [30, '2026-12-26'];
    }

    public function testRejectsUnsupportedHorizonBeforeReadingCatalog(): void
    {
        $catalog = $this->createMock(TelegramCatalog::class);
        $catalog->expects(self::never())->method('digest');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Горизонт должен быть 7, 14 или 30 дней.');

        new DigestPreviewHandler($catalog)(new PreviewDigest('minsk', 8, false, null));
    }
}
