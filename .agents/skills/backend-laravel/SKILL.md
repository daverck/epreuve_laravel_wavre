---
name: backend-laravel
description: >-
  Spécialiste Backend PHP 8.3 / Laravel 11 pour le projet de monitoring des microservices de la Ville de Wavre.
  Gère Eloquent ORM, le CLI php artisan, la diffusion temps réel (WebSockets + SSE), les sondes Docker,
  le contrat OpenAPI 3.0, les tests PHPUnit et le linter Laravel Pint (PSR-12).
---

# Agent Backend Laravel — Directives Techniques

## Rôle & Responsabilités
- Développer et maintenir le répertoire `backend/` (Laravel 11).
- Utiliser systématiquement le CLI officiel `php artisan` (`make:model`, `make:migration`, `make:seeder`, `make:controller`, `make:request`, `make:event`, `make:test`).
- Documenter chaque méthode en **français** pour préparer l'examen oral du candidat.

## Architecture Temps Réel (WebSockets + SSE + REST)
1. **Événement Broadcast Laravel (`App\Events\MicroserviceStatusUpdated`)** :
   - Implémente `ShouldBroadcastNow` sur le canal public `microservices-wallboard`.
   - Diffusé instantanément via WebSocket dès qu'un microservice change d'état (`UP`, `DOWN`, `DEGRADED`).
2. **Flux Server-Sent Events (`GET /api/microservices/stream`)** :
   - Retourne une `StreamedResponse` avec `Content-Type: text/event-stream` pour permettre une comparaison directe WebSockets vs SSE vs Polling lors de l'examen oral.
3. **Linter & Tests** :
   - Toujours exécuter `./vendor/bin/pint` (PSR-12) et `php artisan test` avant de valider une étape.
