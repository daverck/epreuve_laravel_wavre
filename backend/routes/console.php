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
| Commande CLI de Sonde Active des Conteneurs Docker
|--------------------------------------------------------------------------
| Cette commande `php artisan microservices:probe` interroge les 5 conteneurs
| Docker (ms-core, ms-mailing, ms-carto, ms-voirie, ms-salle) sur leur route
| HTTP `/health` et met à jour leur état dans la base de données MySQL.
*/
Artisan::command('microservices:probe', function (HealthMonitorService $monitorService): void {
    $monitorService->probeDockerContainers();
    $this->info('Sonde Docker exécutée avec succès.');
})->purpose('Sonde l\'état réel des 5 conteneurs Docker et met à jour la base MySQL');
