# Règles de l'Agent Frontend Angular (`frontend/`)

Ce répertoire est sous la responsabilité de l'**Agent Frontend (`frontend-angular`)**.

## Conventions & Outils Obligatoires
1. **Stack Technique** :
   - Angular (Standalone Components) + TypeScript strict + RxJS + CSS moderne (Animations Keyframes haute visibilité pour écran mural TV / Wallboard).
2. **Démonstration Temps Réel (Examen Écrit : WebSockets vs SSE vs Polling)** :
   - Le service `RealtimeMonitoringService` implémente les **3 modes de transport temps réel** :
     - **Mode 1 : WebSockets (`ws://`)** — Canal bidirectionnel temps réel (`< 10 ms`) avec reconnexion automatique et réception instantanée des événements `MicroserviceStatusUpdated`.
     - **Mode 2 : Server-Sent Events (`SSE` — `EventSource`)** — Flux HTTP persistant unidirectionnel (`text/event-stream` sur `/api/microservices/stream`).
     - **Mode 3 : Short Polling (`HTTP GET`)** — Interrogation périodique toutes les 2 secondes (`/api/microservices`).
   - L'interface affiche un **sélecteur interactif du protocole temps réel** (`WebSocket` | `SSE` | `Polling`) dans l'en-tête du Wallboard afin de pouvoir démontrer en direct au jury le comportement de chaque technologie !
3. **Alerte Visuelle Critique & Simulation** :
   - Dès qu'un des 5 microservices (`core`, `mailing`, `carto`, `voirie`, `salle`) passe à `DOWN`, afficher immédiatement un bandeau d'alerte rouge clignotant avec chronomètre de panne et halo lumineux.
   - Chaque carte microservice propose des boutons explicites : **"Simuler Déconnexion"**, **"Simuler Ralentissement"**, **"Simuler Reconnexion"**.
4. **Linter Frontend (`ESLint`)** :
   - Valider le code avec `npm run lint` avant chaque commit.
