# Architecture & Directives Globales — Monitoring Microservices (Ville de Wavre)

Ce projet répond à l'épreuve technique de **Développeur Fullstack à la Ville de Wavre**.
Il est conçu pour être développé et maintenu par deux agents spécialisés (**Agent Backend Laravel** et **Agent Frontend Angular**).

## Règles Impératives Communes
1. **Explicabilité pour l'Examen Oral** :
   - Chaque classe, méthode et bloc complexe doit comporter des **commentaires pédagogiques en français** expliquant le *Pourquoi* (choix d'architecture) en plus du *Comment*.
2. **Architecture Temps Réel : Choix Exclusif des WebSockets (`ws://`)** :
   - Pour répondre au besoin d'afficher **une alerte visuelle rapidement** dès qu'un microservice se déconnecte, nous avons choisi **exclusivement les WebSockets (`ws://localhost:6001` RFC 6455)** (en écartant le Short Polling trop coûteux en requêtes HTTP et les SSE limités à de l'unidirectionnel).
   - Dès qu'une déconnexion ou reconnexion a lieu, Laravel émet l'événement `MicroserviceStatusUpdated` (`ShouldBroadcastNow`) qui est poussé en moins de `5 ms` par WebSocket à tous les écrans connectés.
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
