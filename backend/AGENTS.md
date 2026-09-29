# Règles de l'Agent Backend Laravel (`backend/`)

Ce répertoire est sous la responsabilité de l'**Agent Backend (`backend-laravel`)**.

## Conventions & Outils Obligatoires
1. **CLI Officiel `php artisan`** :
   - Utiliser `php artisan make:*` pour générer les classes (Modèles, Migrations, Seeders, Contrôleurs, FormRequests, Events, Tests).
2. **Linter PHP Officiel (`Laravel Pint`)** :
   - Exécuter `./vendor/bin/pint` (configuré via `backend/pint.json` en norme **PSR-12**) avant chaque commit.
3. **Architecture Temps Réel Hybride (WebSockets + SSE + Polling)** :
   - Toute modification de statut d'un microservice (`UP`, `DOWN`, `DEGRADED`) passe par `App\Services\HealthMonitorService`.
   - `HealthMonitorService` déclenche l'événement `App\Events\MicroserviceStatusUpdated` (`ShouldBroadcastNow`) pour le serveur **WebSocket** ET met à jour le cache/flux lu par l'endpoint **SSE** (`GET /api/microservices/stream`).
4. **Tests Automatisés (`php artisan test`)** :
   - Chaque route de `/api/microservices` doit être couverte par `tests/Feature/MicroserviceMonitoringTest.php`.
