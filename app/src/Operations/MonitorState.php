<?php

declare(strict_types=1);

namespace App\Operations;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;

final readonly class MonitorState
{
    public const int STALE_AFTER = 15 * 60;

    public function __construct(
        private Filesystem $filesystem,
        #[Autowire('%kernel.logs_dir%/external-monitor.json')]
        private string $path,
    ) {
    }

    public function save(MonitorReport $report): void
    {
        $now = time();
        $this->filesystem->dumpFile($this->path, json_encode([
            'receivedAt' => $now,
            'siteHealthy' => $report->siteHealthy,
            'importHealthy' => $report->siteHealthy ? $report->importHealthy : null,
            'lastSiteFailureAt' => null === $report->lastSiteFailureAt ? null : min($now, $report->lastSiteFailureAt),
        ], JSON_THROW_ON_ERROR));
    }

    public function latest(): ?array
    {
        if (!is_readable($this->path)) {
            return null;
        }

        $json = @file_get_contents($this->path, length: 1024);
        $state = false === $json ? null : json_decode($json, true);
        if (!is_int($state['receivedAt'] ?? null)) {
            return null;
        }

        return $state + ['stale' => $state['receivedAt'] < time() - self::STALE_AFTER];
    }
}
