<?php

declare(strict_types=1);

namespace App\Telegram\Application;

use App\Telegram\Domain\DigestHorizon;
use Psr\Clock\ClockInterface;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class DigestPreviewHandler
{
    public function __construct(private TelegramCatalog $catalog, private ClockInterface $clock = new NativeClock())
    {
    }

    public function __invoke(PreviewDigest $query): Digest
    {
        $horizon = DigestHorizon::fromDays($query->days);
        $from = $this->clock->now()->setTimezone(new \DateTimeZone('Europe/Minsk'));
        $until = $from->modify(sprintf('+%d days', $horizon->value));

        return $this->catalog->digest($query, $from, $until);
    }
}
