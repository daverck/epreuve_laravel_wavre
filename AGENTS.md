# Architecture & Directives Globales — Monitoring Microservices (Ville de Wavre)

Ce projet répond à l'épreuve technique de **Développeur Fullstack à la Ville de Wavre**.
Il est conçu pour être développé et maintenu par deux agents spécialisés (**Agent Backend Laravel** et **Agent Frontend Angular**).

## Règles Impératives Communes
1. **Explicabilité pour l'Examen Oral** :
   - Chaque classe, méthode et bloc complexe doit comporter des **commentaires pédagogiques en français** expliquant le *Pourquoi* (choix d'architecture) en plus du *Comment*.
2. **Architecture Temps Réel (Lien avec l'Examen Écrit : Polling vs SSE vs WebSockets)** :
   - Le projet implémente et démontre la maîtrise des **3 approches temps réel** :
     1. **WebSockets (`ws://` Bidirectionnel)** : Utilisé pour diffuser instantanément (`< 10 ms`) l'événement `MicroserviceStatusUpdated` à tous les écrans connectés dès qu'une déconnexion ou reconnexion a lieu (idéal en environnement multi-écrans où un opérateur simule une panne pendant qu'un écran TV mural affiche le statut).
     2. **Server-Sent Events (`SSE` — `GET /api/microservices/stream`)** : Flux HTTP unidirectionnel `text/event-stream` poussant les changements d'état du serveur vers le client (l'alternative HTTP native la plus légère pour un écran passif).
     3. **Short Polling (`GET /api/microservices`)** : Fallback résilient garantissant la continuité de supervision même derrière un pare-feu bloquant les connexions persistantes.
3. **Les 5 Microservices Officiels de la Ville de Wavre** :
   - `core` : Sert l'interface client (Service critique — impacte l'ensemble du système s'il est `DOWN`)
   - `mailing` : Envoie les mails pour les différents processus
   - `carto` : Gère le CRUD des données cartographiques
   - `voirie` : Gère les arrêtés de police et les autorisations de stationnement
   - `salle` : Gère les locations de salles
4. **Qualité, Linters & Git** :
   - Utiliser `php artisan` pour toute génération backend et `./vendor/bin/pint` (PSR-12) pour le linting PHP.
   - Utiliser `ESLint` pour le linting TypeScript/Angular.
   - Effectuer des commits Git atomiques à chaque étape.
