<?php

declare(strict_types=1);

namespace App\Tests\Admin;

use App\Identity\Domain\User;
use App\Identity\Domain\UserRole;
use App\Operations\MonitorState;
use App\Operations\RecentLogs;
use App\Operations\ServerMetrics;
use App\Operations\Sql\SqlJournal;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Security\Core\User\UserProviderInterface;

final class OperationsTest extends WebTestCase
{
    public function testOnlyAdministratorsCanSeeTheJournalsAndChecks(): void
    {
        $client = self::createClient();
        $client->disableReboot();
        $filesystem = new Filesystem();
        $file = $filesystem->tempnam(sys_get_temp_dir(), 'afisha-operations-');
        $filesystem->remove($file);
        $filesystem->mkdir($file);
        try {
            $editor = User::create('editor@example.test', 'hash', UserRole::Editor);
            $admin = User::create('admin@example.test', 'hash', UserRole::Admin);
            $provider = $this->createStub(UserProviderInterface::class);
            $provider->method('supportsClass')->willReturn(true);
            $provider->method('refreshUser')->willReturnArgument(0);
            self::getContainer()->set('security.user.provider.concrete.app_users', $provider);
            $db = $this->createStub(Connection::class);
            $db->method('fetchOne')->willReturnOnConsecutiveCalls(1, 0);
            self::getContainer()->set(Connection::class, $db);
            self::getContainer()->set(RecentLogs::class, new RecentLogs($file));
            self::getContainer()->set(MonitorState::class, new MonitorState($filesystem, $file.'/report.json'));
            $metrics = [];
            foreach (['cpu_busy_percent' => 42.5, 'memory_available_percent' => 54, 'disk_available_percent' => 10, 'server_sample_timestamp_seconds' => time()] as $name => $value) {
                $metrics[] = ['metric' => ['__name__' => 'afisha:'.$name, 'mountpoint' => '/data'], 'value' => [time(), (string) $value]];
            }
            self::getContainer()->set(ServerMetrics::class, new ServerMetrics(
                new MockHttpClient(new MockResponse(json_encode(['status' => 'success', 'data' => ['result' => $metrics]], JSON_THROW_ON_ERROR))),
                'http://prometheus:9090',
                '/data',
            ));

            $client->request('GET', '/admin/operations');
            self::assertResponseRedirects('/admin/login');

            $client->loginUser($editor);
            $client->request('GET', '/admin/operations');
            self::assertResponseStatusCodeSame(403);
            $client->request('GET', '/admin/sql-queries');
            self::assertResponseStatusCodeSame(403);
            $client->request('GET', '/admin/http-requests');
            self::assertResponseStatusCodeSame(403);

            $filesystem->dumpFile($file.'/collector.log.1', "Предыдущий журнал\n");
            $filesystem->dumpFile($file.'/collector-2026-11-20T09-00-00.000000000.log', "Журнал после ротации\n");
            $filesystem->dumpFile($file.'/collector.log', "Сборщик запущен <script>\nНезавершённая запись");
            $query = SqlJournal::PREFIX.json_encode(['query' => 'SELECT name FROM source WHERE slug = ?', 'durationMs' => 1.5, 'ok' => false], JSON_THROW_ON_ERROR);
            $filesystem->dumpFile($file.'/php.log', implode("\n", [
                json_encode(['ts' => 1, 'level' => 'error', 'msg' => 'Ошибка PHP'], JSON_THROW_ON_ERROR),
                json_encode(['ts' => 2, 'msg' => $query], JSON_THROW_ON_ERROR),
                json_encode(['ts' => 3, 'msg' => '[debug] Executing statement: old-secret'], JSON_THROW_ON_ERROR),
                '',
            ]));
            $filesystem->dumpFile($file.'/access-2026-11-20T11-00-00.000-time.log', json_encode([
                'ts' => 100.5,
                'request' => [
                    'host' => 'afisha.example.test',
                    'method' => 'GET',
                    'uri' => '/category/concert?token=test-query-secret',
                    'remote_ip' => '192.0.2.10',
                    'client_ip' => '2001:db8::1',
                ],
                'status' => 404,
                'user_agent' => 'Test <script>',
            ], JSON_THROW_ON_ERROR)."\n");
            $filesystem->dumpFile($file.'/report.json', json_encode([
                'receivedAt' => time() - MonitorState::STALE_AFTER - 10,
                'siteHealthy' => true,
                'importHealthy' => false,
                'lastSiteFailureAt' => 1,
            ], JSON_THROW_ON_ERROR));

            $client->loginUser($admin);
            $client->request('GET', '/admin/operations');
            self::assertResponseIsSuccessful();
            self::assertSelectorTextContains('#main', 'Актуален');
            self::assertSelectorTextContains('#main', 'Данные устарели');
            self::assertSelectorTextContains('.admin-resource-grid', '42,5 %');
            self::assertSelectorTextContains('.admin-resource-grid', '10,0 % /data');
            self::assertSelectorTextContains('#main', 'Ошибка PHP');
            self::assertSelectorTextContains('#main', 'Предыдущий журнал');
            self::assertSelectorTextContains('#main', 'Журнал после ротации');
            self::assertStringContainsString('&lt;script&gt;', (string) $client->getResponse()->getContent());
            self::assertStringNotContainsString('Незавершённая запись', (string) $client->getResponse()->getContent());
            self::assertStringNotContainsString('SELECT name', (string) $client->getResponse()->getContent());
            self::assertStringNotContainsString('old-secret', (string) $client->getResponse()->getContent());
            self::assertTrue($client->getResponse()->headers->hasCacheControlDirective('no-store'));

            $client->request('GET', '/admin/http-requests');
            self::assertResponseIsSuccessful();
            self::assertSelectorTextContains('tbody', 'GET /category/concert');
            self::assertSelectorTextContains('tbody', 'afisha.example.test');
            self::assertSelectorTextContains('tbody', '404');
            self::assertSelectorTextContains('tbody', '2001:db8::1');
            self::assertStringContainsString('Test &lt;script&gt;', (string) $client->getResponse()->getContent());
            self::assertStringNotContainsString('test-query-secret', (string) $client->getResponse()->getContent());
            self::assertStringNotContainsString('192.0.2.10', (string) $client->getResponse()->getContent());
            self::assertTrue($client->getResponse()->headers->hasCacheControlDirective('no-store'));

            $filesystem->dumpFile($file.'/access.log', json_encode([
                'ts' => 120,
                'request' => ['uri' => '/older-in-active-file'],
                'status' => 200,
            ], JSON_THROW_ON_ERROR)."\n".str_repeat(json_encode([
                'ts' => 140,
                'request' => ['uri' => '/recent'],
                'status' => 200,
                'user_agent' => str_repeat('x', 500),
            ], JSON_THROW_ON_ERROR)."\n", 300));

            $client->request('GET', '/admin/http-requests', ['q' => 'SCRIPT']);
            self::assertResponseIsSuccessful();
            self::assertSelectorTextContains('tbody', 'Test <script>');
            self::assertSelectorTextNotContains('tbody', '/recent');

            $client->request('GET', '/admin/http-requests', ['q' => '^/older-in-active-file$', 'regex' => 1]);
            self::assertResponseIsSuccessful();
            self::assertSelectorTextContains('tbody', '/older-in-active-file');
            self::assertSelectorTextNotContains('tbody', '/recent');

            $client->request('GET', '/admin/http-requests', ['q' => '[', 'regex' => 1]);
            self::assertResponseIsSuccessful();
            self::assertSelectorTextContains('[role="alert"]', 'Некорректное регулярное выражение');
            self::assertSelectorNotExists('tbody');

            $client->request('GET', '/admin/sql-queries');
            self::assertResponseIsSuccessful();
            self::assertSelectorTextContains('#main pre', 'SELECT name FROM source WHERE slug = ?');
            self::assertSelectorTextContains('#main', '1,50 мс');
            self::assertSelectorTextContains('#main', 'Ошибка');
            self::assertStringNotContainsString('old-secret', (string) $client->getResponse()->getContent());
            self::assertTrue($client->getResponse()->headers->hasCacheControlDirective('no-store'));
        } finally {
            $filesystem->remove($file);
        }
    }

    public function testOnlyAuthenticatedReportsAreStored(): void
    {
        $oldToken = $_ENV['AFISHA_MONITOR_REPORT_TOKEN'] ?? null;
        $_ENV['AFISHA_MONITOR_REPORT_TOKEN'] = 'test-monitor-token';
        $filesystem = new Filesystem();
        $path = $filesystem->tempnam(sys_get_temp_dir(), 'afisha-report-');
        try {
            $client = self::createClient();
            $client->disableReboot();
            $state = new MonitorState($filesystem, $path);
            self::getContainer()->set(MonitorState::class, $state);
            $payload = ['siteHealthy' => false, 'importHealthy' => true, 'lastSiteFailureAt' => time() - 30];

            $client->jsonRequest('POST', '/healthz/report', $payload);
            self::assertResponseStatusCodeSame(403);
            self::assertNull($state->latest());

            $auth = ['HTTP_AUTHORIZATION' => 'Bearer test-monitor-token'];
            $client->jsonRequest('POST', '/healthz/report', ['siteHealthy' => 'yes'], $auth);
            self::assertResponseStatusCodeSame(422);
            self::assertNull($state->latest());

            $client->jsonRequest('POST', '/healthz/report', $payload, $auth);
            self::assertResponseStatusCodeSame(204);
            self::assertFalse($state->latest()['siteHealthy']);
            self::assertNull($state->latest()['importHealthy']);
            self::assertSame($payload['lastSiteFailureAt'], $state->latest()['lastSiteFailureAt']);
            self::assertFalse($state->latest()['stale']);
        } finally {
            $filesystem->remove($path);
            if (null === $oldToken) {
                unset($_ENV['AFISHA_MONITOR_REPORT_TOKEN']);
            } else {
                $_ENV['AFISHA_MONITOR_REPORT_TOKEN'] = $oldToken;
            }
        }
    }
}
