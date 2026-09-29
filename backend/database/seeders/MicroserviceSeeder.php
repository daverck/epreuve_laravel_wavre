<?php

namespace Database\Seeders;

use App\Models\HealthCheck;
use App\Models\Microservice;
use App\Models\ServiceLog;
use Illuminate\Database\Seeder;

/**
 * Seeder initialisant les 5 microservices internes de la Ville de Wavre
 * ainsi que leurs premiers journaux d'audit (`service_logs`) et sondes (`health_checks`).
 */
class MicroserviceSeeder extends Seeder
{
    /**
     * Exécute le peuplement initial de la base de données.
     */
    public function run(): void
    {
        $now = now();

        $services = [
            [
                'slug' => 'core',
                'name' => 'Core (Interface Client)',
                'description' => "Sert l'interface client",
                'container_name' => 'wavre-ms-core',
                'health_url' => 'http://ms-core:8081/health',
                'port' => 8081,
                'status' => 'UP',
                'is_simulated_down' => false,
                'is_critical' => true,
                'response_time_ms' => 11,
                'uptime_percentage' => 99.99,
            ],
            [
                'slug' => 'mailing',
                'name' => 'Mailing (Notifications)',
                'description' => 'Envoie les mails pour les différents processus',
                'container_name' => 'wavre-ms-mailing',
                'health_url' => 'http://ms-mailing:8082/health',
                'port' => 8082,
                'status' => 'UP',
                'is_simulated_down' => false,
                'is_critical' => false,
                'response_time_ms' => 18,
                'uptime_percentage' => 99.95,
            ],
            [
                'slug' => 'carto',
                'name' => 'Carto (Données SIG)',
                'description' => 'Gère le CRUD des données cartographiques',
                'container_name' => 'wavre-ms-carto',
                'health_url' => 'http://ms-carto:8083/health',
                'port' => 8083,
                'status' => 'UP',
                'is_simulated_down' => false,
                'is_critical' => false,
                'response_time_ms' => 24,
                'uptime_percentage' => 99.90,
            ],
            [
                'slug' => 'voirie',
                'name' => 'Voirie (Police & Stationnement)',
                'description' => 'Gère les arrêtés de police et les autorisations de stationnement',
                'container_name' => 'wavre-ms-voirie',
                'health_url' => 'http://ms-voirie:8084/health',
                'port' => 8084,
                'status' => 'UP',
                'is_simulated_down' => false,
                'is_critical' => false,
                'response_time_ms' => 15,
                'uptime_percentage' => 99.94,
            ],
            [
                'slug' => 'salle',
                'name' => 'Salle (Locations Communales)',
                'description' => 'Gère les locations de salles',
                'container_name' => 'wavre-ms-salle',
                'health_url' => 'http://ms-salle:8085/health',
                'port' => 8085,
                'status' => 'UP',
                'is_simulated_down' => false,
                'is_critical' => false,
                'response_time_ms' => 14,
                'uptime_percentage' => 99.97,
            ],
        ];

        foreach ($services as $item) {
            $microservice = Microservice::updateOrCreate(
                ['slug' => $item['slug']],
                array_merge($item, [
                    'last_checked_at' => $now,
                    'last_status_change_at' => $now->copy()->subHours(4),
                ])
            );

            // Journal d'initialisation système pour chaque microservice
            ServiceLog::create([
                'microservice_id' => $microservice->id,
                'previous_status' => 'UP',
                'new_status' => 'UP',
                'trigger_source' => 'SYSTEM_INIT',
                'message' => sprintf(
                    'Initialisation du conteneur [%s] sur le port %d (statut nominal UP).',
                    $microservice->container_name,
                    $microservice->port
                ),
                'downtime_duration_seconds' => null,
            ]);

            // Sonde HTTP initiale nominale
            HealthCheck::create([
                'microservice_id' => $microservice->id,
                'http_status_code' => 200,
                'latency_ms' => $item['response_time_ms'],
                'is_healthy' => true,
                'checked_at' => $now,
            ]);
        }
    }
}
