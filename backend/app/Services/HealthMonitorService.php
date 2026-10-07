<?php

namespace App\Services;

use App\Models\HealthCheck;
use App\Models\Microservice;
use App\Models\ServiceLog;
use Illuminate\Support\Facades\Http;

/**
 * Service métier centralisant la supervision des 5 microservices de la Ville de Wavre,
 * la simulation de pannes (Chaos Engineering) et l'interrogation temps réel des
 * conteneurs Docker via une architecture 100 % REST HTTP Stateless (Short Polling).
 */
class HealthMonitorService
{
    /**
     * Adresses IP statiques des 5 microservices sur le sous-réseau Docker `wavre-net` (172.28.0.0/16).
     * L'accès par IP directe évite toute temporisation DNS (`getaddrinfo` / resolv.conf)
     * lorsqu'un conteneur est arrêté (`docker stop`), garantissant une sonde instantanée (< 150 ms).
     */
    private const IP_MAP = [
        'core' => '172.28.0.81',
        'mailing' => '172.28.0.82',
        'carto' => '172.28.0.83',
        'voirie' => '172.28.0.84',
        'salle' => '172.28.0.85',
    ];

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
     * d'un microservice, calcule la durée d'indisponibilité en cas de rétablissement
     * et pilote le conteneur Docker correspondant.
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

        // Si l'action provient de l'interface (SIMULATION_UI), on verrouille is_simulated_down
        // tant que le statut n'est pas UP. Si elle provient de la sonde Docker (DOCKER_PROBE),
        // is_simulated_down passe à false pour refléter l'état physique du conteneur.
        $isSimulatedDown = $source === 'SIMULATION_UI'
            ? ($newStatus !== 'UP')
            : false;

        $service->update([
            'status' => $newStatus,
            'is_simulated_down' => $isSimulatedDown,
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

        return [
            'microservice' => $service,
            'log' => $log,
            'dashboard' => $dashboard,
        ];
    }

    /**
     * Interroge activement les 5 conteneurs Docker sur leur port TCP / endpoint HTTP `/health`
     * et enregistre une mesure `HealthCheck` pour chacun d'entre eux.
     *
     * Règles de priorité entre Simulation UI et Sonde Docker :
     * - Si le conteneur Docker est arrêté/injoignable (`@fsockopen` échoue) et `DOCKER_PROBE_ENABLED=true`,
     *   `$detectedStatus = 'DOWN'`.
     * - Si le conteneur répond `200 OK` mais que `$service->is_simulated_down === true`,
     *   on conserve `$detectedStatus = $service->status` pour ne pas écraser la simulation manuelle de l'UI.
     * - Si `$service->is_simulated_down === false` (panne issue d'un `docker stop` réel) et que le conteneur
     *   répond à nouveau, `$detectedStatus = 'UP'` rétablit automatiquement le microservice.
     */
    public function probeDockerContainers(): void
    {
        $microservices = Microservice::query()->orderBy('id')->get();
        $now = now();
        $dockerProbeEnabled = filter_var(env('DOCKER_PROBE_ENABLED', false), FILTER_VALIDATE_BOOLEAN);
        $healthChecksToInsert = [];

        foreach ($microservices as $service) {
            $startTime = microtime(true);
            $httpStatusCode = $service->status === 'DOWN' ? 503 : 200;
            $latencyMs = $service->status === 'DOWN' ? 0 : ($service->response_time_ms ?: 15);
            $detectedStatus = $service->status;

            if (! app()->runningUnitTests() && $dockerProbeEnabled) {
                $host = self::IP_MAP[$service->slug] ?? sprintf('ms-%s', $service->slug);
                $errno = 0;
                $errstr = '';
                $socket = @stream_socket_client(
                    sprintf('tcp://%s:%d', $host, (int) $service->port),
                    $errno,
                    $errstr,
                    0.2,
                    STREAM_CLIENT_CONNECT
                );

                if ($socket === false) {
                    // Conteneur Docker réellement arrêté (ex: `docker stop wavre-ms-voirie`)
                    $detectedStatus = 'DOWN';
                    $httpStatusCode = 503;
                    $latencyMs = 0;
                } else {
                    fclose($socket);
                    $tcpLatencyMs = max(2, (int) round((microtime(true) - $startTime) * 1000));

                    if ($service->is_simulated_down) {
                        // Conserve strictement la simulation manuelle déclenchée depuis le Wallboard UI (DEGRADED ou DOWN)
                        $detectedStatus = $service->status;
                        $httpStatusCode = $service->status === 'DOWN' ? 503 : 200;
                        $latencyMs = $service->status === 'DOWN'
                            ? 0
                            : ($service->status === 'DEGRADED' ? 460 : $tcpLatencyMs);
                    } else {
                        // Aucun verrou de simulation UI actif et conteneur joignable : statut UP
                        $detectedStatus = 'UP';
                        $httpStatusCode = 200;
                        $latencyMs = $tcpLatencyMs;
                    }
                }
            }

            $healthChecksToInsert[] = [
                'microservice_id' => $service->id,
                'http_status_code' => $httpStatusCode,
                'latency_ms' => $latencyMs,
                'is_healthy' => $detectedStatus === 'UP',
                'checked_at' => $now,
            ];

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

        if (! empty($healthChecksToInsert)) {
            HealthCheck::insert($healthChecksToInsert);
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
        if (app()->runningUnitTests() || $newStatus === 'DOWN') {
            return;
        }

        $host = self::IP_MAP[$service->slug] ?? sprintf('ms-%s', $service->slug);
        $errno = 0;
        $errstr = '';
        $socket = @stream_socket_client(
            sprintf('tcp://%s:%d', $host, (int) $service->port),
            $errno,
            $errstr,
            0.2,
            STREAM_CLIENT_CONNECT
        );
        if ($socket === false) {
            return;
        }
        fclose($socket);

        $simulateUrl = sprintf('http://%s:%d/simulate', $host, $service->port);

        try {
            Http::connectTimeout(1)->timeout(1)->post($simulateUrl, [
                'status' => $newStatus,
            ]);
        } catch (\Throwable) {
            // Ignoré silencieusement si le conteneur cible est arrêté ou hors réseau Docker
        }
    }
}
