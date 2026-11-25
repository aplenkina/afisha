<?php

declare(strict_types=1);

namespace App\Tests\Telegram;

use App\Catalog\Application\ShowCategoryEvents\CategoryEventView;
use App\Telegram\Application\Digest;
use App\Telegram\Application\PreviewDigest;
use App\Telegram\Application\TelegramCatalog;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class PreviewDigestCommandTest extends KernelTestCase
{
    public function testPassesFiltersToMessengerAndFormatsDigest(): void
    {
        $catalog = $this->createMock(TelegramCatalog::class);
        $catalog->expects(self::once())->method('digest')->with(
            self::callback(static fn (PreviewDigest $query): bool => 'minsk' === $query->citySlug
                && 14 === $query->days && $query->onlyFree && 'music' === $query->categoryAlias),
        )->willReturn(new Digest(
            'minsk',
            'Минск',
            'music',
            14,
            true,
            new \DateTimeImmutable('2026-11-25 12:00 Europe/Minsk'),
            new \DateTimeImmutable('2026-12-09 12:00 Europe/Minsk'),
            [new CategoryEventView(
                7, 'Концерт', 'concert', new \DateTimeImmutable('2026-11-26 15:00 UTC'),
                null, 'Минск', 'Сцена', null, null, null, true, null, null,
            )],
        ));
        $kernel = self::bootKernel();
        self::getContainer()->set(TelegramCatalog::class, $catalog);
        $tester = new CommandTester(new Application($kernel)->find('app:digest:preview'));

        $tester->execute(['city' => 'minsk', '--days' => '14', '--category' => 'music', '--free' => true]);

        $tester->assertCommandIsSuccessful();
        $output = $tester->getDisplay();
        self::assertStringContainsString('Афиша · Минск · 14 дн.', $output);
        self::assertStringContainsString("26.11.2026\n18:00 — Концерт\nСцена · Бесплатно", $output);
        self::assertStringContainsString('http://localhost/event/concert', $output);
        self::assertStringContainsString('http://localhost/minsk/music?period=custom&from=2026-11-25&to=2026-12-09&free=1', $output);
    }

    public function testInvalidDaysReturnInvalidExitCode(): void
    {
        $kernel = self::bootKernel();
        $catalog = $this->createMock(TelegramCatalog::class);
        $catalog->expects(self::never())->method('digest');
        self::getContainer()->set(TelegramCatalog::class, $catalog);
        $tester = new CommandTester(new Application($kernel)->find('app:digest:preview'));

        self::assertSame(Command::INVALID, $tester->execute(['city' => 'minsk', '--days' => 'bad']));
        self::assertStringContainsString('Горизонт должен быть 7, 14 или 30 дней.', $tester->getDisplay());
    }

    protected static function getKernelClass(): string
    {
        return TelegramTestKernel::class;
    }
}
