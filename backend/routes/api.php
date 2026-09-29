<?php

use App\Http\Controllers\Api\MicroserviceController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Routes API REST HTTP Stateless — Supervision Microservices (Ville de Wavre)
|--------------------------------------------------------------------------
| Préfixées automatiquement par `/api` via `bootstrap/app.php`.
| Alimente l'écran mural (Wallboard Angular) via Short Polling REST HTTP.
*/

// Documentation interactive OpenAPI 3.0.3 / Swagger UI
Route::get('/openapi.yaml', [MicroserviceController::class, 'openapiSpec'])
    ->name('api.openapi');
Route::get('/docs', [MicroserviceController::class, 'swaggerUi'])
    ->name('api.docs');

// Journal global des incidents et rétablissements
Route::get('/incidents', [MicroserviceController::class, 'incidents'])
    ->name('api.incidents');

// Supervision et Chaos Engineering des 5 microservices
Route::prefix('microservices')->group(function (): void {
    Route::get('/', [MicroserviceController::class, 'index'])
        ->name('api.microservices.index');

    // Actions globales (Rétablissement général / Sonde Docker active)
    Route::post('/simulate-all', [MicroserviceController::class, 'simulateAll'])
        ->name('api.microservices.simulate-all');
    Route::post('/ping', [MicroserviceController::class, 'ping'])
        ->name('api.microservices.ping');

    // Opérations ciblées par identifiant métier (slug : core, mailing, carto, voirie, salle)
    Route::get('/{slug}', [MicroserviceController::class, 'show'])
        ->name('api.microservices.show');
    Route::post('/{slug}/simulate', [MicroserviceController::class, 'simulate'])
        ->name('api.microservices.simulate');
});
