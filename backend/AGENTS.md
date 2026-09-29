# Règles de l'Agent Backend Laravel (`backend/`)

Ce répertoire est sous la responsabilité de l'**Agent Backend (`backend-laravel`)**.

## Conventions & Outils Obligatoires
1. **CLI Officiel `php artisan`** :
   - Utiliser `php artisan make:*` pour générer les classes (Modèles, Migrations, Seeders, Contrôleurs, FormRequests, Tests).
2. **Linter PHP Officiel (`Laravel Pint`)** :
   - Exécuter `./vendor/bin/pint` (configuré via `backend/pint.json` en norme **PSR-12**) avant chaque commit.
3. **Architecture de Supervision REST Stateless** :
   - L'endpoint `GET /api/microservices` appelle `HealthMonitorService::getDashboardPayload(true)`, qui interroge directement les 5 conteneurs Docker (`ms-core` à `ms-salle`), met à jour la base MySQL et renvoie l'état courant et les derniers incidents.
   - Les routes `POST /api/microservices/{slug}/simulate` et `POST /api/microservices/simulate-all` permettent de simuler la déconnexion (`DOWN`), le ralentissement (`DEGRADED`) ou la reconnexion (`UP`) de chaque microservice.
4. **Tests Automatisés (`php artisan test`)** :
   - Chaque route de `/api/microservices` doit être couverte par `tests/Feature/MicroserviceMonitoringTest.php`.
