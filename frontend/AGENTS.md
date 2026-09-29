# Règles de l'Agent Frontend Angular (`frontend/`)

Ce répertoire est sous la responsabilité de l'**Agent Frontend (`frontend-angular`)**.

## Conventions & Outils Obligatoires
1. **Stack Technique** :
   - Angular (Standalone Components + Signals + `HttpClient`) + TypeScript strict + CSS moderne (Animations Keyframes haute visibilité pour écran mural TV / Wallboard).
2. **Supervision par Short Polling HTTP REST (2s)** :
   - Le service `RealtimeMonitoringService` interroge toutes les **2 secondes** l'endpoint `GET /api/microservices?probe=true` via `HttpClient` pour maintenir l'écran mural synchronisé avec l'état réel des conteneurs Docker et de la base de données.
3. **Alerte Visuelle Critique & Simulation** :
   - Dès qu'un des 5 microservices (`core`, `mailing`, `carto`, `voirie`, `salle`) passe à `DOWN`, afficher immédiatement un bandeau d'alerte rouge clignotant avec chronomètre de panne et halo lumineux.
   - Chaque carte microservice propose des boutons explicites : **"Simuler Déconnexion"**, **"Simuler Ralentissement"**, **"Simuler Reconnexion"**.
4. **Linter Frontend (`ESLint`)** :
   - Valider le code avec `npm run lint` avant chaque commit.
