<?php

declare(strict_types=1);

namespace App\Operations;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class ServerMetrics
{
    private const string QUERY = '{__name__=~"afisha:cpu_busy_percent|afisha:memory_available_percent|afisha:disk_available_percent|afisha:server_sample_timestamp_seconds"}';
    private const int MAX_SAMPLE_AGE = 120;

    public function __construct(
        private HttpClientInterface $client,
        #[Autowire('%env(default::AFISHA_PROMETHEUS_URL)%')]
        private ?string $url,
        #[Autowire('%env(default::AFISHA_RESOURCE_DISK_MOUNT)%')]
        private ?string $diskMount,
    ) {
    }

    public function latest(): array
    {
        if (!$this->url) {
            return ['status' => 'disabled'];
        }

        try {
            $response = $this->client->request('GET', rtrim($this->url, '/').'/api/v1/query', [
                'query' => ['query' => self::QUERY, 'timeout' => '1s'],
                'timeout' => 1.0,
                'max_duration' => 2.0,
                'max_redirects' => 0,
            ])->toArray();
        } catch (ExceptionInterface) {
            return ['status' => 'unavailable'];
        }

        $series = $response['data']['result'] ?? null;
        if ('success' !== ($response['status'] ?? null) || !is_array($series)) {
            return ['status' => 'unavailable'];
        }

        $disk = $this->diskMount ?: '/';
        $values = [];
        foreach ($series as $entry) {
            $name = $entry['metric']['__name__'] ?? '';
            $value = $entry['value'][1] ?? null;
            if (!is_numeric($value) || !is_finite((float) $value)) {
                continue;
            }
            if ('afisha:disk_available_percent' === $name && $disk !== ($entry['metric']['mountpoint'] ?? null)) {
                continue;
            }
            $values[$name] = (float) $value;
        }

        $sampledAt = (int) ($values['afisha:server_sample_timestamp_seconds'] ?? 0);
        $now = time();
        if ($sampledAt < $now - self::MAX_SAMPLE_AGE || $sampledAt > $now + 30) {
            return ['status' => 'unavailable'];
        }

        return [
            'status' => 'ready',
            'sampledAt' => $sampledAt,
            'cpu' => $values['afisha:cpu_busy_percent'] ?? null,
            'memory' => $values['afisha:memory_available_percent'] ?? null,
            'disk' => $values['afisha:disk_available_percent'] ?? null,
            'mountpoint' => $disk,
        ];
    }
}
