<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SimulateMicroserviceRequest;
use App\Models\Microservice;
use App\Models\ServiceLog;
use App\Services\HealthMonitorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Contrôleur API principal gérant la supervision des 5 microservices de la Ville de Wavre,
 * la simulation de pannes (Chaos Engineering), le flux temps réel SSE (Server-Sent Events)
 * et la documentation interactive OpenAPI 3.0 / Swagger UI.
 */
class MicroserviceController extends Controller
{
    public function __construct(
        private readonly HealthMonitorService $monitorService,
    ) {
    }

    /**
     * GET /api/microservices
     * Retourne l'état temps réel des 5 microservices, le résumé global d'alerte
     * et les 20 derniers incidents pour l'écran mural (Wallboard Angular).
     */
    public function index(Request $request): JsonResponse
    {
        $probeDocker = $request->boolean('probe', false);
        $payload = $this->monitorService->getDashboardPayload($probeDocker);

        return response()->json($payload);
    }

    /**
     * GET /api/microservices/{slug}
     * Retourne les détails complets d'un microservice spécifique avec son historique
     * de logs (`service_logs`) et ses dernières sondes HTTP (`health_checks`).
     */
    public function show(string $slug): JsonResponse
    {
        $microservice = Microservice::query()
            ->where('slug', $slug)
            ->firstOrFail();

        $logs = $microservice->logs()->with('microservice')->limit(25)->get();
        $healthChecks = $microservice->healthChecks()->limit(20)->get();

        $data = array_merge($microservice->toArray(), [
            'logs' => $logs->values()->toArray(),
            'health_checks' => $healthChecks->values()->toArray(),
        ]);

        return response()->json([
            'data' => $data,
        ]);
    }

    /**
     * POST /api/microservices/{slug}/simulate
     * Simule la déconnexion (`DOWN`), le ralentissement (`DEGRADED`) ou la reconnexion (`UP`)
     * d'un microservice spécifique et diffuse l'alerte en temps réel (WebSocket + SSE).
     */
    public function simulate(SimulateMicroserviceRequest $request, string $slug): JsonResponse
    {
        $microservice = Microservice::query()
            ->where('slug', $slug)
            ->firstOrFail();

        $validated = $request->validated();
        $newStatus = strtoupper((string) $validated['status']);
        $reason = $validated['reason'] ?? null;

        $result = $this->monitorService->simulateStatus(
            $microservice,
            $newStatus,
            $reason,
            'SIMULATION_UI'
        );

        return response()->json([
            'message' => sprintf(
                'Le microservice [%s] est maintenant en statut %s.',
                $microservice->slug,
                $newStatus
            ),
            'data' => $result['microservice'],
            'log' => $result['log'],
            'summary' => $result['dashboard']['summary'],
        ]);
    }

    /**
     * POST /api/microservices/simulate-all
     * Applique un statut global (`UP`, `DOWN` ou `DEGRADED`) aux 5 microservices en un seul clic
     * (ex: rétablissement général de tous les conteneurs depuis le Wallboard).
     */
    public function simulateAll(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', 'in:UP,DOWN,DEGRADED'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $targetStatus = strtoupper((string) $validated['status']);
        $reason = $validated['reason'] ?? sprintf(
            'Action globale depuis le Wallboard : passage de tous les services à %s.',
            $targetStatus
        );

        $microservices = Microservice::query()->orderBy('id')->get();
        foreach ($microservices as $service) {
            if ($service->status !== $targetStatus || $targetStatus !== 'UP') {
                $this->monitorService->simulateStatus(
                    $service,
                    $targetStatus,
                    $reason,
                    'SIMULATION_UI'
                );
            }
        }

        return response()->json($this->monitorService->getDashboardPayload(false));
    }

    /**
     * POST /api/microservices/ping
     * Déclenche immédiatement une sonde HTTP active (`/health`) vers les 5 conteneurs Docker.
     */
    public function ping(): JsonResponse
    {
        $payload = $this->monitorService->getDashboardPayload(true);

        return response()->json($payload);
    }

    /**
     * GET /api/incidents
     * Retourne la liste chronologique des événements de déconnexion et reconnexion.
     */
    public function incidents(Request $request): JsonResponse
    {
        $limit = max(1, min(100, (int) $request->query('limit', 25)));

        $incidents = ServiceLog::with('microservice')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        return response()->json([
            'data' => $incidents->values()->toArray(),
        ]);
    }

    /**
     * GET /api/microservices/stream
     * Endpoint Server-Sent Events (SSE) diffusant l'état JSON des microservices en flux continu
     * (`Content-Type: text/event-stream`). Permet au Wallboard Angular de recevoir les mises
     * à jour en temps réel et de comparer WebSockets (RFC 6455) vs SSE vs HTTP Polling.
     */
    public function stream(Request $request): StreamedResponse
    {
        $iterations = (int) $request->query('iterations', 1);
        $iterations = max(1, min(5, $iterations));

        return response()->stream(function () use ($iterations): void {
            for ($i = 0; $i < $iterations; $i++) {
                $payload = $this->monitorService->getDashboardPayload(false);
                $json = json_encode($payload, JSON_UNESCAPED_UNICODE);

                echo 'id: '.uniqid('sse-', true)."\n";
                echo "event: wallboard.update\n";
                echo "retry: 2000\n";
                echo "data: {$json}\n\n";

                if (ob_get_level() > 0) {
                    @ob_flush();
                }
                flush();

                if ($i + 1 < $iterations && ! app()->runningUnitTests()) {
                    sleep(1);
                }
            }
        }, 200, [
            'Content-Type' => 'text/event-stream; charset=UTF-8',
            'Cache-Control' => 'no-cache, no-transform',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
            'Access-Control-Allow-Origin' => '*',
        ]);
    }

    /**
     * GET /api/openapi.yaml
     * Sert la spécification formelle OpenAPI 3.0.3 au format YAML.
     */
    public function openapiSpec(): Response
    {
        $candidatePaths = [
            base_path('../docs/openapi.yaml'),
            '/var/www/docs/openapi.yaml',
            base_path('openapi.yaml'),
        ];

        foreach ($candidatePaths as $path) {
            if (is_file($path) && is_readable($path)) {
                return response((string) file_get_contents($path), 200, [
                    'Content-Type' => 'application/yaml; charset=UTF-8',
                    'Access-Control-Allow-Origin' => '*',
                ]);
            }
        }

        return response('openapi: 3.0.3', 200, [
            'Content-Type' => 'application/yaml; charset=UTF-8',
        ]);
    }

    /**
     * GET /api/docs
     * Affiche l'interface interactive Swagger UI permettant de tester les endpoints
     * de supervision et de simulation directement depuis le navigateur.
     */
    public function swaggerUi(): Response
    {
        $html = <<<'HTML'
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Swagger UI — API Monitoring Microservices (Ville de Wavre)</title>
    <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@5/swagger-ui.css">
    <style>
        body { margin: 0; background: #0f172a; color: #f8fafc; font-family: system-ui, sans-serif; }
        .wavre-banner {
            background: linear-gradient(90deg, #0284c7, #1e293b);
            padding: 14px 24px;
            border-bottom: 1px solid #334155;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .wavre-banner h1 { margin: 0; font-size: 1.15rem; color: #ffffff; }
        .wavre-banner span { font-size: 0.85rem; color: #bae6fd; }
        #swagger-ui { background: #ffffff; min-height: calc(100vh - 56px); }
    </style>
</head>
<body>
    <div class="wavre-banner">
        <h1>Ville de Wavre — Contrat OpenAPI 3.0 (Supervision des 5 Microservices)</h1>
        <span>Laravel 11 REST + WebSockets + SSE</span>
    </div>
    <div id="swagger-ui"></div>
    <script src="https://unpkg.com/swagger-ui-dist@5/swagger-ui-bundle.js"></script>
    <script>
        window.onload = () => {
            window.ui = SwaggerUIBundle({
                url: '/api/openapi.yaml',
                dom_id: '#swagger-ui',
                deepLinking: true,
                presets: [SwaggerUIBundle.presets.apis]
            });
        };
    </script>
</body>
</html>
HTML;

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
        ]);
    }
}
