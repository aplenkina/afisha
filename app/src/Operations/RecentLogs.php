<?php

declare(strict_types=1);

namespace App\Operations;

use App\Operations\Sql\SqlJournal;
use App\Shared\Presentation\ListSearch;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class RecentLogs
{
    private const int LIMIT = 100;
    private const int MAX_BYTES = 128 * 1024;
    private const int MAX_LOG_FILE_BYTES = 5 * 1024 * 1024;

    public function __construct(
        #[Autowire('%kernel.logs_dir%')]
        private string $directory,
    ) {
    }

    public function php(?ListSearch $search = null): array
    {
        return $this->phpEntries(static function (string $message, array $entry): ?string {
            if (str_starts_with($message, SqlJournal::PREFIX)
                || preg_match('/^(?:\[[a-z]+]\s+)?(?:Executing (?:statement|query)|Connecting with parameters)\b/i', $message)) {
                return null;
            }

            $time = (new \DateTimeImmutable())->setTimestamp((int) ($entry['ts'] ?? 0))
                ->setTimezone(new \DateTimeZone('Europe/Minsk'));

            return sprintf('%s [%s] %s', $time->format('Y-m-d H:i:s'), $entry['level'] ?? 'info', $message);
        }, $search);
    }

    public function sql(?ListSearch $search = null): array
    {
        return $this->phpEntries(static function (string $message, array $entry): ?array {
            if (!str_starts_with($message, SqlJournal::PREFIX)) {
                return null;
            }

            $query = json_decode(substr($message, strlen(SqlJournal::PREFIX)), true);
            if (!is_string($query['query'] ?? null) || !is_numeric($query['durationMs'] ?? null) || !is_bool($query['ok'] ?? null)) {
                return null;
            }

            return ['at' => (int) ($entry['ts'] ?? 0)] + $query;
        }, $search, static fn (array $row): array => [$row['query'], $row['durationMs'], $row['ok'] ? 'Выполнен' : 'Ошибка']);
    }

    private function phpEntries(\Closure $parse, ?ListSearch $search, ?\Closure $fields = null): array
    {
        return $this->tail($this->rotatedPaths('php'), static function (string $line) use ($parse): mixed {
            $entry = json_decode($line, true);

            return is_string($entry['msg'] ?? null) ? $parse($entry['msg'], $entry) : null;
        }, $search, $fields);
    }

    public function collector(?ListSearch $search = null): array
    {
        return $this->tail([...$this->rotatedPaths('collector'), $this->directory.'/collector.log.1'], search: $search);
    }

    public function requests(?ListSearch $search = null): array
    {
        return $this->tail($this->rotatedPaths('access'), static function (string $line): ?array {
            $entry = json_decode($line, true);
            $request = $entry['request'] ?? [];
            $uri = $request['uri'] ?? null;
            $status = $entry['status'] ?? null;
            $timestamp = $entry['ts'] ?? null;

            if (!is_string($uri) || !is_int($status) || $status < 100 || $status > 599 || !is_numeric($timestamp)) {
                return null;
            }

            $ip = $request['client_ip'] ?? $request['remote_ip'] ?? '';
            $method = $request['method'] ?? null;
            $host = $request['host'] ?? null;
            $userAgent = $entry['user_agent'] ?? null;

            return [
                'at' => (int) $timestamp,
                'method' => is_string($method) ? $method : '',
                'host' => is_string($host) ? $host : '',
                'path' => explode('?', $uri, 2)[0],
                'status' => $status,
                'ip' => filter_var($ip, FILTER_VALIDATE_IP) ?: null,
                'userAgent' => is_string($userAgent) ? $userAgent : '',
            ];
        }, $search, static fn (array $row): array => [
            $row['path'], $row['host'], $row['host'].$row['path'], $row['method'], $row['status'], $row['ip'], $row['userAgent'],
        ]);
    }

    /** @return list<string> */
    private function rotatedPaths(string $name): array
    {
        $base = $this->directory.'/'.$name;
        $backups = glob($base.'-*.log') ?: [];
        rsort($backups, SORT_STRING);

        return [$base.'.log', ...$backups];
    }

    /** @param list<string> $paths Newest file first. */
    private function tail(array $paths, ?\Closure $parse = null, ?ListSearch $search = null, ?\Closure $fields = null): array
    {
        if ('' === $search?->query && null === $search->error) {
            $search = null;
        }

        $lines = [];
        $maxBytes = null === $search ? self::MAX_BYTES : self::MAX_LOG_FILE_BYTES;
        foreach ($paths as $path) {
            if ($search?->shouldStop()) {
                break;
            }
            $file = @fopen($path, 'rb');
            if (false === $file) {
                continue;
            }

            try {
                $size = fstat($file)['size'] ?? 0;
                $offset = max(0, $size - $maxBytes - 1);
                if (0 !== fseek($file, $offset) || 0 === $size) {
                    continue;
                }
                $chunk = stream_get_contents($file, $size - $offset);
                if (false === $chunk) {
                    continue;
                }
            } finally {
                fclose($file);
            }

            $parts = explode("\n", $chunk);
            if ($offset > 0) {
                array_shift($parts);
            }
            // The last fragment may still be written by the logging process.
            array_pop($parts);
            foreach (array_reverse($parts) as $part) {
                if ($search?->shouldStop()) {
                    break 2;
                }
                if ('' === $part) {
                    continue;
                }
                $entry = null === $parse ? $part : $parse($part);
                if (null === $entry || (null !== $search && !$search->matches($fields ? $fields($entry) : [$entry]))) {
                    continue;
                }

                $lines[] = $entry;
                if (count($lines) >= self::LIMIT) {
                    break 2;
                }
            }
        }

        return $lines;
    }
}
