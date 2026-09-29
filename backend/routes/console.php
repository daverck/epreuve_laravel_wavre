<?php

declare(strict_types=1);

use App\Services\HealthMonitorService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function (): void {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Commande CLI de Sonde Active Docker -> Diffusion WebSocket Temps Réel
|--------------------------------------------------------------------------
| Cette commande `php artisan microservices:probe` interroge les 5 conteneurs
| Docker (ms-core, ms-mailing, ms-carto, ms-voirie, ms-salle) sur le réseau
| interne Docker. Si un conteneur est éteint (`docker stop wavre-ms-voirie`)
| ou rallumé (`docker start wavre-ms-voirie`), elle met à jour la base MySQL
| et pousse immédiatement l'événement `MicroserviceStatusUpdated` par WebSocket
| (`ws://localhost:6001`) vers tous les écrans Angular connectés.
*/
Artisan::command('microservices:probe', function (HealthMonitorService $monitorService): void {
    $monitorService->probeDockerContainers();
    $this->info('Sonde Docker exécutée avec succès.');
})->purpose('Sonde l\'état réel des 5 conteneurs Docker et diffuse tout changement via WebSocket');
