<?php

declare(strict_types=1);

namespace App\Admin;

use App\Operations\HealthChecks;
use App\Operations\MonitorState;
use App\Operations\RecentLogs;
use App\Operations\ServerMetrics;
use App\Shared\Presentation\ListSearchFactory;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class OperationsController extends AbstractController
{
    #[AdminRoute('/operations', name: 'operations', options: ['methods' => 'GET'])]
    public function index(HealthChecks $checks, MonitorState $monitor, RecentLogs $logs, ServerMetrics $metrics, ListSearchFactory $searches): Response
    {
        $databaseHealthy = $checks->database();
        $search = $searches->create(750);

        return $this->render('admin/operations/index.html.twig', [
            'database' => $databaseHealthy,
            'import' => $databaseHealthy ? $checks->import() : null,
            'localCheckedAt' => time(),
            'external' => $monitor->latest(),
            'resources' => $metrics->latest(),
            'search' => $search,
            'journals' => [
                'Приложение · PHP' => $logs->php($search),
                'Сборщик событий · Go' => $logs->collector($search),
            ],
        ], new Response(headers: ['Cache-Control' => 'private, no-store']));
    }

    #[AdminRoute('/sql-queries', name: 'sql_queries', options: ['methods' => 'GET'])]
    public function queries(RecentLogs $logs, ListSearchFactory $searches): Response
    {
        $search = $searches->create(750);

        return $this->render('admin/operations/sql.html.twig', [
            'queries' => $logs->sql($search),
            'search' => $search,
        ], new Response(headers: ['Cache-Control' => 'private, no-store']));
    }

    #[AdminRoute('/http-requests', name: 'http_requests', options: ['methods' => 'GET'])]
    public function requests(RecentLogs $logs, ListSearchFactory $searches): Response
    {
        $search = $searches->create(750);

        return $this->render('admin/operations/requests.html.twig', [
            'requests' => $logs->requests($search),
            'search' => $search,
        ], new Response(headers: ['Cache-Control' => 'private, no-store']));
    }
}
