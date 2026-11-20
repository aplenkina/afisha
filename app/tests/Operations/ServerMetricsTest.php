<?php

declare(strict_types=1);

namespace App\Tests\Operations;

use App\Operations\ServerMetrics;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class ServerMetricsTest extends TestCase
{
    public function testReportsStaleAndUnavailableMetrics(): void
    {
        $metrics = ['status' => 'success', 'data' => ['result' => [
            ['metric' => ['__name__' => 'afisha:server_sample_timestamp_seconds'], 'value' => [time(), (string) (time() - 121)]],
        ]]];
        $client = new MockHttpClient([
            new MockResponse(json_encode($metrics, JSON_THROW_ON_ERROR)),
            new MockResponse('', ['http_code' => 503]),
        ]);
        $configured = new ServerMetrics($client, 'http://prometheus:9090', null);

        self::assertSame(['status' => 'unavailable'], $configured->latest());
        self::assertSame(['status' => 'unavailable'], $configured->latest());
        self::assertSame(['status' => 'disabled'], (new ServerMetrics($client, null, null))->latest());
    }
}
