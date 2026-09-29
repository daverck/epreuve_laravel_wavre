# Règles de l'Agent Frontend Angular (`frontend/`)

Ce répertoire est sous la responsabilité de l'**Agent Frontend (`frontend-angular`)**.

## Conventions & Outils Obligatoires
1. **Stack Technique** :
   - Angular (Standalone Components + Signals) + TypeScript strict + CSS moderne (Animations Keyframes haute visibilité pour écran mural TV / Wallboard).
2. **Architecture Temps Réel : 100 % WebSockets (`ws://localhost:6001`)** :
   - Le service `RealtimeMonitoringService` effectue un chargement initial (`GET /api/microservices`) puis maintient une connexion persistante **WebSocket (`ws://localhost:6001`)** pour recevoir instantanément (`< 5 ms`) les événements `MicroserviceStatusUpdated` dès qu'un microservice se déconnecte ou se reconnecte.
3. **Alerte Visuelle Critique & Simulation** :
   - Dès qu'un des 5 microservices (`core`, `mailing`, `carto`, `voirie`, `salle`) passe à `DOWN`, afficher immédiatement un bandeau d'alerte rouge clignotant avec chronomètre de panne et halo lumineux.
   - Chaque carte microservice propose des boutons explicites : **"Simuler Déconnexion"**, **"Simuler Ralentissement"**, **"Simuler Reconnexion"**.
4. **Linter Frontend (`ESLint`)** :
   - Valider le code avec `npm run lint` avant chaque commit.
