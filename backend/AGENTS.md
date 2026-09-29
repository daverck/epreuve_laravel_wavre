# Règles de l'Agent Backend Laravel (`backend/`)

Ce répertoire est sous la responsabilité de l'**Agent Backend (`backend-laravel`)**.

## Conventions & Outils Obligatoires
1. **CLI Officiel `php artisan`** :
   - Utiliser `php artisan make:*` pour générer les classes (Modèles, Migrations, Seeders, Contrôleurs, FormRequests, Events, Tests).
2. **Linter PHP Officiel (`Laravel Pint`)** :
   - Exécuter `./vendor/bin/pint` (configuré via `backend/pint.json` en norme **PSR-12**) avant chaque commit.
3. **Architecture Temps Réel : 100 % WebSockets (`ws://`)** :
   - Toute modification de statut d'un microservice (`UP`, `DOWN`, `DEGRADED`) passe par `App\Services\HealthMonitorService`.
   - `HealthMonitorService` déclenche l'événement `App\Events\MicroserviceStatusUpdated` (`ShouldBroadcastNow`) qui est diffusé instantanément (`< 5 ms`) via le serveur **WebSocket (`ws-hub:6001`)** à tous les écrans connectés.
4. **Tests Automatisés (`php artisan test`)** :
   - Chaque route de `/api/microservices` et la diffusion de l'événement `MicroserviceStatusUpdated` doivent être couvertes par `tests/Feature/MicroserviceMonitoringTest.php`.
