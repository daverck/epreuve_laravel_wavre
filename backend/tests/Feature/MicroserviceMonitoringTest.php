<?php

namespace Tests\Feature;

use App\Events\MicroserviceStatusUpdated;
use Database\Seeders\MicroserviceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Suite de tests fonctionnels (Feature Tests PHPUnit) validant l'intégralité
 * du backend de supervision des 5 microservices de la Ville de Wavre :
 * - API REST de supervision (`/api/microservices`)
 * - Simulation de pannes (`DOWN`, `DEGRADED`) et reconnexion (`UP`) avec calcul du downtime
 * - Diffusion temps réel WebSocket (`MicroserviceStatusUpdated`) et Server-Sent Events (`/api/microservices/stream`)
 * - Contrat OpenAPI 3.0 & Swagger UI (`/api/openapi.yaml`, `/api/docs`)
 */
class MicroserviceMonitoringTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Initialise les 5 microservices de la Ville de Wavre avant chaque test.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MicroserviceSeeder::class);
    }

    /**
     * Test 1 : Vérifie que la route principale retourne exactement les 5 microservices
     * (`core`, `mailing`, `carto`, `voirie`, `salle`) avec un état global `OPERATIONAL`.
     */
    public function test_it_lists_all_five_wavre_microservices_and_operational_summary(): void
    {
        $response = $this->getJson('/api/microservices');

        $response->assertOk()
            ->assertJsonPath('summary.total_services', 5)
            ->assertJsonPath('summary.up_count', 5)
            ->assertJsonPath('summary.down_count', 0)
            ->assertJsonPath('summary.degraded_count', 0)
            ->assertJsonPath('summary.global_status', 'OPERATIONAL')
            ->assertJsonPath('summary.has_active_alert', false)
            ->assertJsonPath('summary.core_is_down', false)
            ->assertJsonCount(5, 'microservices');

        $slugs = collect($response->json('microservices'))->pluck('slug')->all();
        $this->assertSame(['core', 'mailing', 'carto', 'voirie', 'salle'], $slugs);
    }

    /**
     * Test 2 : Vérifie que la simulation d'une déconnexion (`DOWN`) sur un microservice
     * met à jour la base de données, crée un `ServiceLog`, active l'alerte et émet l'événement WebSocket.
     */
    public function test_it_simulates_microservice_disconnection_creates_log_and_triggers_active_alert(): void
    {
        Event::fake([MicroserviceStatusUpdated::class]);

        $response = $this->postJson('/api/microservices/voirie/simulate', [
            'status' => 'DOWN',
            'reason' => 'Simulation manuelle de panne du service Voirie',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.slug', 'voirie')
            ->assertJsonPath('data.status', 'DOWN')
            ->assertJsonPath('data.is_simulated_down', true)
            ->assertJsonPath('log.previous_status', 'UP')
            ->assertJsonPath('log.new_status', 'DOWN')
            ->assertJsonPath('log.trigger_source', 'SIMULATION_UI')
            ->assertJsonPath('summary.down_count', 1)
            ->assertJsonPath('summary.has_active_alert', true);

        $this->assertDatabaseHas('microservices', [
            'slug' => 'voirie',
            'status' => 'DOWN',
            'is_simulated_down' => true,
        ]);

        $this->assertDatabaseHas('service_logs', [
            'previous_status' => 'UP',
            'new_status' => 'DOWN',
            'trigger_source' => 'SIMULATION_UI',
            'message' => 'Simulation manuelle de panne du service Voirie',
        ]);

        Event::assertDispatched(MicroserviceStatusUpdated::class, function (MicroserviceStatusUpdated $event): bool {
            return $event->microservice->slug === 'voirie'
                && $event->microservice->status === 'DOWN';
        });
    }

    /**
     * Test 3 : Vérifie que la chute du microservice critique `core` (Interface Client)
     * déclenche immédiatement l'indicateur `core_is_down = true` et `CRITICAL_ALERT`.
     */
    public function test_it_triggers_critical_alert_when_core_microservice_goes_down(): void
    {
        $response = $this->postJson('/api/microservices/core/simulate', [
            'status' => 'DOWN',
            'reason' => 'Arrêt critique du conteneur Core (Interface Client)',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.slug', 'core')
            ->assertJsonPath('data.is_critical', true)
            ->assertJsonPath('summary.core_is_down', true)
            ->assertJsonPath('summary.global_status', 'CRITICAL_ALERT')
            ->assertJsonPath('summary.has_active_alert', true);
    }

    /**
     * Test 4 : Vérifie que la reconnexion (`DOWN` -> `UP`) calcule précisément la durée
     * d'indisponibilité (`downtime_duration_seconds`) et remet `downtime_seconds` à 0.
     */
    public function test_it_calculates_downtime_duration_when_microservice_reconnects_to_up(): void
    {
        // 1. Mise hors-ligne du service Mailing
        $this->postJson('/api/microservices/mailing/simulate', [
            'status' => 'DOWN',
            'reason' => 'Panne SMTP simulée',
        ])->assertOk();

        // 2. Avance dans le temps de 45 secondes pour simuler la durée de la panne
        $this->travel(45)->seconds();

        // 3. Reconnexion du service Mailing (passage à UP)
        $reconnectResponse = $this->postJson('/api/microservices/mailing/simulate', [
            'status' => 'UP',
            'reason' => 'Relance du serveur de notifications Mailing',
        ]);

        $reconnectResponse->assertOk()
            ->assertJsonPath('data.slug', 'mailing')
            ->assertJsonPath('data.status', 'UP')
            ->assertJsonPath('data.is_simulated_down', false)
            ->assertJsonPath('data.downtime_seconds', 0)
            ->assertJsonPath('log.previous_status', 'DOWN')
            ->assertJsonPath('log.new_status', 'UP');

        $downtimeRecorded = (int) $reconnectResponse->json('log.downtime_duration_seconds');
        $this->assertGreaterThanOrEqual(45, $downtimeRecorded);
    }

    /**
     * Test 5 : Vérifie que le FormRequest renvoie une erreur HTTP 422 si le statut demandé
     * n'appartient pas à l'énumération autorisée (`UP`, `DOWN`, `DEGRADED`).
     */
    public function test_it_rejects_invalid_simulation_status_with_422_validation_error(): void
    {
        $response = $this->postJson('/api/microservices/carto/simulate', [
            'status' => 'UNKNOWN_STATE',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    /**
     * Test 6 : Vérifie l'endpoint `POST /api/microservices/simulate-all` permettant
     * de dégrader puis de rétablir l'ensemble des 5 microservices en un clic.
     */
    public function test_it_simulates_and_restores_all_microservices_at_once(): void
    {
        $degradeResponse = $this->postJson('/api/microservices/simulate-all', [
            'status' => 'DEGRADED',
            'reason' => 'Test de charge général sur le réseau communal',
        ]);

        $degradeResponse->assertOk()
            ->assertJsonPath('summary.degraded_count', 5)
            ->assertJsonPath('summary.global_status', 'WARNING')
            ->assertJsonPath('summary.has_active_alert', true);

        $restoreResponse = $this->postJson('/api/microservices/simulate-all', [
            'status' => 'UP',
            'reason' => 'Rétablissement général de tous les microservices',
        ]);

        $restoreResponse->assertOk()
            ->assertJsonPath('summary.up_count', 5)
            ->assertJsonPath('summary.down_count', 0)
            ->assertJsonPath('summary.global_status', 'OPERATIONAL')
            ->assertJsonPath('summary.has_active_alert', false);
    }

    /**
     * Test 7 : Vérifie que l'endpoint Server-Sent Events (`GET /api/microservices/stream`)
     * diffuse un flux `text/event-stream` valide contenant l'état JSON du Wallboard.
     */
    public function test_it_streams_real_time_status_via_server_sent_events_sse_endpoint(): void
    {
        $response = $this->get('/api/microservices/stream?iterations=1');

        $response->assertOk();
        $this->assertStringContainsString(
            'text/event-stream',
            (string) $response->headers->get('Content-Type')
        );

        $streamedContent = $response->streamedContent();
        $this->assertStringContainsString('event: wallboard.update', $streamedContent);
        $this->assertStringContainsString('"total_services":5', $streamedContent);
    }

    /**
     * Test 8 : Vérifie les routes de détail (`/api/microservices/{slug}`), d'historique
     * des incidents (`/api/incidents`), de sonde active (`/api/microservices/ping`)
     * et de documentation OpenAPI/Swagger (`/api/openapi.yaml` & `/api/docs`).
     */
    public function test_it_serves_details_incidents_ping_and_swagger_documentation(): void
    {
        $this->getJson('/api/microservices/salle')
            ->assertOk()
            ->assertJsonPath('data.slug', 'salle')
            ->assertJsonPath('data.port', 8085);

        $this->getJson('/api/incidents?limit=10')
            ->assertOk()
            ->assertJsonStructure(['data']);

        $this->postJson('/api/microservices/ping')
            ->assertOk()
            ->assertJsonPath('summary.total_services', 5);

        $this->get('/api/openapi.yaml')
            ->assertOk();

        $this->get('/api/docs')
            ->assertOk()
            ->assertSee('SwaggerUIBundle', false);
    }
}
