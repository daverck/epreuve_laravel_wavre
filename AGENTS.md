# Architecture & Directives Globales — Monitoring Microservices (Ville de Wavre)

Ce projet répond à l'épreuve technique de **Développeur Fullstack à la Ville de Wavre**.
Il est conçu pour être développé et maintenu par deux agents spécialisés (**Agent Backend Laravel** et **Agent Frontend Angular**).

## Règles Impératives Communes
1. **Explicabilité pour l'Examen Oral** :
   - Chaque classe, méthode et bloc complexe doit comporter des **commentaires pédagogiques en français** expliquant le *Pourquoi* (choix d'architecture) en plus du *Comment*.
2. **Architecture de Supervision : Choix du Short Polling HTTP REST Stateless (2s) — Principe KISS / YAGNI** :
   - Pour superviser les **5 microservices internes** de la Ville de Wavre et afficher une alerte visuelle rapide dès qu'un conteneur se déconnecte, l'écran Angular interroge l'API REST Laravel (`GET /api/microservices?probe=true`) toutes les **2 secondes**.
   - À chaque appel, Laravel sonde directement les 5 conteneurs Docker (`ms-core` à `ms-salle`), met à jour MySQL et retourne l'état consolidé, sans complexité inutile de serveur WebSocket ou de démon supplémentaire.
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
