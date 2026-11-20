<?php

declare(strict_types=1);

namespace App\Operations;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

final readonly class MonitorReportController
{
    public function __construct(
        private MonitorState $state,
        #[Autowire('%env(default::AFISHA_MONITOR_REPORT_TOKEN)%')]
        private ?string $token,
    ) {
    }

    #[Route('/healthz/report', name: 'health_report', methods: ['POST'], format: 'json', stateless: true)]
    public function __invoke(Request $request, #[MapRequestPayload(acceptFormat: 'json')] MonitorReport $report): Response
    {
        if (!$this->token || !hash_equals('Bearer '.$this->token, $request->headers->get('Authorization', ''))) {
            return new Response(status: Response::HTTP_FORBIDDEN, headers: ['Cache-Control' => 'no-store']);
        }

        $this->state->save($report);

        return new Response(status: Response::HTTP_NO_CONTENT, headers: ['Cache-Control' => 'no-store']);
    }
}
