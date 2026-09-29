<?php

namespace App\Services;

use App\Events\MicroserviceStatusUpdated;
use App\Models\HealthCheck;
use App\Models\Microservice;
use App\Models\ServiceLog;
use Illuminate\Support\Facades\Http;

/**
 * Service métier centralisant la supervision des 5 microservices de la Ville de Wavre,
 * la simulation de pannes (Chaos Engineering), l'interrogation des conteneurs Docker
 * et la diffusion temps réel via WebSockets (`MicroserviceStatusUpdated`).
 */
class HealthMonitorService
{
    /**
     * Construit la charge utile complète destinée à l'écran de supervision (Wallboard Angular).
     *
     * @param  bool  $probeDocker  Si true, interroge d'abord les conteneurs Docker via HTTP (/health).
     * @return array<string, mixed>
     */
    public function getDashboardPayload(bool $probeDocker = false): array
    {
        if ($probeDocker) {
            $this->probeDockerContainers();
        }

        $microservices = Microservice::query()
            ->orderBy('id')
            ->get();

        $totalServices = $microservices->count();
        $upCount = $microservices->where('status', 'UP')->count();
        $downCount = $microservices->where('status', 'DOWN')->count();
        $degradedCount = $microservices->where('status', 'DEGRADED')->count();

        $coreService = $microservices->firstWhere('slug', 'core');
        $coreIsDown = $coreService !== null && $coreService->status === 'DOWN';
        $hasActiveAlert = $downCount > 0 || $degradedCount > 0;

        if ($downCount > 0) {
            $globalStatus = 'CRITICAL_ALERT';
        } elseif ($degradedCount > 0) {
            $globalStatus = 'WARNING';
        } else {
            $globalStatus = 'OPERATIONAL';
        }

        $activeServices = $microservices->where('status', '!=', 'DOWN');
        $averageLatencyMs = $activeServices->isNotEmpty()
            ? (int) round((float) $activeServices->avg('response_time_ms'))
            : 0;

        $recentIncidents = ServiceLog::with('microservice')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        return [
            'summary' => [
                'total_services' => $totalServices,
                'up_count' => $upCount,
                'down_count' => $downCount,
                'degraded_count' => $degradedCount,
                'global_status' => $globalStatus,
                'has_active_alert' => $hasActiveAlert,
                'core_is_down' => $coreIsDown,
                'average_latency_ms' => $averageLatencyMs,
                'checked_at' => now()->toIso8601String(),
            ],
            'microservices' => $microservices->values()->toArray(),
            'recent_incidents' => $recentIncidents->values()->toArray(),
        ];
    }

    /**
     * Simule la déconnexion (`DOWN`), le ralentissement (`DEGRADED`) ou la reconnexion (`UP`)
     * d'un microservice, calcule la durée d'indisponibilité en cas de rétablissement,
     * pilote le conteneur Docker correspondant et diffuse l'alerte en temps réel via WebSocket.
     *
     * @return array{microservice: Microservice, log: ServiceLog, dashboard: array<string, mixed>}
     */
    public function simulateStatus(
        Microservice $service,
        string $newStatus,
        ?string $reason = null,
        string $source = 'SIMULATION_UI'
    ): array {
        $previousStatus = $service->status;
        $now = now();

        // Calcul de la durée d'indisponibilité (downtime) lors du passage de DOWN à UP
        $downtimeDurationSeconds = null;
        if ($previousStatus === 'DOWN' && $newStatus === 'UP') {
            if ($service->last_status_change_at !== null) {
                $elapsed = (int) abs($now->diffInSeconds($service->last_status_change_at));
                $downtimeDurationSeconds = max(1, $elapsed);
            } else {
                $downtimeDurationSeconds = 1;
            }
        }

        // Simulation réaliste du temps de réponse et du taux de disponibilité
        $simulatedLatency = match ($newStatus) {
            'DOWN' => 0,
            'DEGRADED' => 460,
            default => 14,
        };

        $uptime = (float) $service->uptime_percentage;
        if ($newStatus === 'DOWN') {
            $uptime = max(90.00, round($uptime - 0.15, 2));
        } elseif ($newStatus === 'DEGRADED') {
            $uptime = max(95.00, round($uptime - 0.05, 2));
        }

        $service->update([
            'status' => $newStatus,
            'is_simulated_down' => $newStatus !== 'UP',
            'response_time_ms' => $simulatedLatency,
            'uptime_percentage' => $uptime,
            'last_checked_at' => $now,
            'last_status_change_at' => $now,
        ]);

        // Construction d'un message d'audit explicite en français si non fourni
        $logMessage = filled($reason)
            ? (string) $reason
            : $this->buildDefaultTransitionMessage($service, $previousStatus, $newStatus, $downtimeDurationSeconds);

        $log = ServiceLog::create([
            'microservice_id' => $service->id,
            'previous_status' => $previousStatus,
            'new_status' => $newStatus,
            'trigger_source' => $source,
            'message' => $logMessage,
            'downtime_duration_seconds' => $downtimeDurationSeconds,
        ]);
        $log->load('microservice');

        // Enregistrement d'une sonde de santé reflétant le nouvel état
        HealthCheck::create([
            'microservice_id' => $service->id,
            'http_status_code' => $newStatus === 'DOWN' ? 503 : 200,
            'latency_ms' => $simulatedLatency,
            'is_healthy' => $newStatus === 'UP',
            'checked_at' => $now,
        ]);

        // Pilotage du conteneur Docker (ms-{slug}) en environnement conteneurisé
        $this->notifyDockerContainerSimulation($service, $newStatus);

        $service->refresh();
        $dashboard = $this->getDashboardPayload(false);

        // Diffusion de l'événement Broadcast Laravel et push vers le Hub WebSocket (port 6001)
        MicroserviceStatusUpdated::dispatch($service, $log, $dashboard);
        $this->broadcastToWebsocketHub($service, $log, $dashboard);

        return [
            'microservice' => $service,
            'log' => $log,
            'dashboard' => $dashboard,
        ];
    }

    /**
     * Interroge activement les 5 conteneurs Docker sur leur endpoint HTTP `/health`
     * et enregistre une mesure `HealthCheck` pour chacun d'entre eux.
     */
    public function probeDockerContainers(): void
    {
        $microservices = Microservice::query()->orderBy('id')->get();
        $now = now();

        foreach ($microservices as $service) {
            $startTime = microtime(true);
            $httpStatusCode = 200;
            $latencyMs = $service->response_time_ms ?: 15;
            $detectedStatus = $service->status;

            if (! app()->runningUnitTests()) {
                try {
                    $response = Http::timeout(0.5)->get($service->health_url);
                    $latencyMs = max(1, (int) round((microtime(true) - $startTime) * 1000));
                    $httpStatusCode = $response->status();

                    if ($response->successful()) {
                        $bodyStatus = strtoupper((string) $response->json('status', 'UP'));
                        $detectedStatus = in_array($bodyStatus, ['UP', 'DOWN', 'DEGRADED'], true)
                            ? $bodyStatus
                            : 'UP';
                    } else {
                        $detectedStatus = 'DOWN';
                    }
                } catch (\Throwable) {
                    // Si le conteneur Docker n'est pas joignable (ex: exécution locale hors Docker),
                    // on conserve l'état simulé courant pour garantir la continuité de la démonstration.
                    $httpStatusCode = $service->status === 'DOWN' ? 503 : 200;
                    $latencyMs = $service->status === 'DOWN' ? 0 : ($service->response_time_ms ?: 15);
                    $detectedStatus = $service->status;
                }
            } else {
                $httpStatusCode = $service->status === 'DOWN' ? 503 : 200;
                $latencyMs = $service->status === 'DOWN' ? 0 : ($service->response_time_ms ?: 15);
            }

            HealthCheck::create([
                'microservice_id' => $service->id,
                'http_status_code' => $httpStatusCode,
                'latency_ms' => $latencyMs,
                'is_healthy' => $detectedStatus === 'UP',
                'checked_at' => $now,
            ]);

            if ($detectedStatus !== $service->status) {
                $this->simulateStatus(
                    $service,
                    $detectedStatus,
                    sprintf('Changement d\'état détecté par la sonde Docker sur [%s].', $service->container_name),
                    'DOCKER_PROBE'
                );
            } else {
                $service->update([
                    'response_time_ms' => $latencyMs,
                    'last_checked_at' => $now,
                ]);
            }
        }
    }

    /**
     * Génère un libellé explicatif en français lors d'une transition d'état.
     */
    private function buildDefaultTransitionMessage(
        Microservice $service,
        string $previousStatus,
        string $newStatus,
        ?int $downtimeSeconds
    ): string {
        if ($newStatus === 'DOWN') {
            return sprintf(
                'ALERTE : Déconnexion du microservice [%s] (%s). Conteneur %s hors-ligne.',
                $service->slug,
                $service->name,
                $service->container_name
            );
        }

        if ($newStatus === 'DEGRADED') {
            return sprintf(
                'AVERTISSEMENT : Latence élevée détectée sur le microservice [%s] (%s).',
                $service->slug,
                $service->name
            );
        }

        if ($previousStatus === 'DOWN' && $downtimeSeconds !== null) {
            return sprintf(
                'RÉTABLISSEMENT : Le microservice [%s] est de nouveau opérationnel (UP) après %d s d\'indisponibilité.',
                $service->slug,
                $downtimeSeconds
            );
        }

        return sprintf(
            'Le microservice [%s] (%s) est opérationnel (statut UP).',
            $service->slug,
            $service->name
        );
    }

    /**
     * Pilote l'état interne du conteneur Docker (`POST http://ms-{slug}:{port}/simulate`)
     * avec un timeout court (0.5s) et gestion d'erreur silencieuse hors Docker.
     */
    private function notifyDockerContainerSimulation(Microservice $service, string $newStatus): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        $simulateUrl = preg_replace('#/health$#', '/simulate', $service->health_url)
            ?: sprintf('http://ms-%s:%d/simulate', $service->slug, $service->port);

        try {
            Http::timeout(0.5)->post($simulateUrl, [
                'status' => $newStatus,
            ]);
        } catch (\Throwable) {
            // Ignoré silencieusement si le conteneur cible est arrêté ou hors réseau Docker
        }
    }

    /**
     * Publie instantanément l'événement vers le Hub WebSocket (`ws-hub:6001/broadcast`)
     * pour pousser la mise à jour aux écrans Wallboard connectés en RFC 6455.
     *
     * @param  array<string, mixed>  $dashboard
     */
    private function broadcastToWebsocketHub(
        Microservice $service,
        ServiceLog $log,
        array $dashboard
    ): void {
        if (app()->runningUnitTests()) {
            return;
        }

        $wsHubUrl = (string) env('WS_HUB_URL', 'http://ws-hub:6001/broadcast');

        try {
            Http::timeout(0.5)->post($wsHubUrl, [
                'event' => 'microservice.status.updated',
                'channel' => 'microservices-wallboard',
                'microservice' => $service->toArray(),
                'log' => $log->toArray(),
                'dashboard' => $dashboard,
                'timestamp' => now()->toIso8601String(),
            ]);
        } catch (\Throwable) {
            // Ignoré silencieusement si le serveur WebSocket n'est pas joignable
        }
    }
}
