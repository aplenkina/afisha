<?php

declare(strict_types=1);

namespace App\Catalog\Api;

use App\Operations\HealthChecks;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final readonly class HealthController
{
    public function __construct(private HealthChecks $checks)
    {
    }

    #[Route('/healthz', name: 'health_site', methods: ['GET'])]
    public function site(): JsonResponse
    {
        return $this->status($this->checks->database());
    }

    #[Route('/healthz/import', name: 'health_import', methods: ['GET'])]
    public function import(): JsonResponse
    {
        return $this->status($this->checks->import());
    }

    private function status(bool $healthy): JsonResponse
    {
        return new JsonResponse(
            ['status' => $healthy ? 'ok' : 'unavailable'],
            $healthy ? 200 : 503,
            ['Cache-Control' => 'no-store'],
        );
    }
}
