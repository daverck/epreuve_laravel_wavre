# 🏛️ Ville de Wavre — Wallboard de Supervision des Microservices Docker

> **Épreuve Technique — Poste de Développeur Fullstack (Ville de Wavre)**  
> **Stack** : PHP 8.4 (Laravel) • Angular / TypeScript / JS • CSS3 Animations • MySQL 8.0 • Docker Compose • OpenAPI 3.0 • PHPUnit • Laravel Pint & ESLint

---

## 📋 Sommaire

1. [Réflexion Architecturale & Choix Techniques](#-1-réflexion-architecturale--choix-techniques)
   - [1.1 Analyse du besoin métier de la Ville de Wavre](#11-analyse-du-besoin-métier-de-la-ville-de-wavre)
   - [1.2 Stratégie Temps Réel : Pourquoi le Short Polling HTTP REST (2s) ?](#12-stratégie-temps-réel--pourquoi-le-short-polling-http-rest-2s-)
   - [1.3 Modélisation de la Base de Données Relationnelle](#13-modélisation-de-la-base-de-données-relationnelle)
   - [1.4 Ergonomie "Wallboard / NOC TV" & Alerte Visuelle Rapide](#14-ergonomie-wallboard--noc-tv--alerte-visuelle-rapide)
2. [Consignes pour Lancer le Projet](#-2-consignes-pour-lancer-le-projet)
   - [2.1 Démarrage clé-en-main avec Docker Desktop (Recommandé)](#21-démarrage-clé-en-main-avec-docker-desktop-recommandé)
   - [2.2 Accès aux Interfaces & URLs](#22-accès-aux-interfaces--urls)
   - [2.3 Exécution des Tests Automatisés (`php artisan test`) & Linters](#23-exécution-des-tests-automatisés-php-artisan-test--linters)
   - [2.4 Restauration de la Base de Données (Seeder ou Dump SQL)](#24-restauration-de-la-base-de-données-seeder-ou-dump-sql)
3. [Liste Complète des Fonctionnalités](#-3-liste-complète-des-fonctionnalités)
4. [Structure des Livrables du Projet](#-4-structure-des-livrables-du-projet)

---

## 🧠 1. Réflexion Architecturale & Choix Techniques

### 1.1 Analyse du besoin métier de la Ville de Wavre
L'administration communale de la **Ville de Wavre** exploite une application interne conteneurisée reposant sur **5 microservices Docker** aux responsabilités distinctes :

| Microservice (`slug`) | Conteneur Docker | Port | Rôle métier au sein de la Ville de Wavre | Criticité |
| :--- | :--- | :---: | :--- | :---: |
| **`core`** | `wavre-ms-core` | `8081` | **Sert l'interface client** principale de l'application interne | 🔴 **Critique** (Impact global si hors-ligne) |
| **`mailing`** | `wavre-ms-mailing` | `8082` | **Envoie les mails** pour les différents processus métiers | 🟡 Haute |
| **`carto`** | `wavre-ms-carto` | `8083` | **Gère le CRUD des données cartographiques** (SIG communal) | 🟡 Haute |
| **`voirie`** | `wavre-ms-voirie` | `8084` | **Gère les arrêtés de police et les autorisations de stationnement** | 🟡 Haute |
| **`salle`** | `wavre-ms-salle` | `8085` | **Gère les locations de salles** communales et événements | 🟡 Haute |

**Objectif double** :
1. Disposer d'un **écran mural (Wallboard) connecté sur une URL** permettant de visualiser en un coup d'œil l'état de chaque microservice et de déclencher **une alerte visuelle immédiate** dès qu'un microservice se déconnecte.
2. Intégrer directement sur l'interface **des boutons de simulation** permettant de provoquer la **déconnexion (`DOWN`)**, la **dégradation (`DEGRADED`)** et la **reconnexion (`UP`)** de chaque microservice afin de valider le comportement du système d'alerte en conditions réelles.

---

### 1.2 Stratégie Temps Réel : Pourquoi le Short Polling HTTP REST (2s) ?

Pour rafraîchir l'écran mural et détecter rapidement la déconnexion d'un microservice, nous avons comparé **Short Polling HTTP**, **Server-Sent Events (SSE)** et **WebSockets** selon les principes d'architecture **KISS** (*Keep It Simple, Stupid*) et **YAGNI** :

1. **Pourquoi écarter les WebSockets et les SSE ici ?** :
   - Un conteneur Docker qui s'arrête (`docker stop wavre-ms-voirie`) ne peut pas émettre d'événement au moment où il meurt : le backend doit **obligatoirement** sonder activement les conteneurs à intervalle régulier.
   - Ajouter un serveur WebSocket dédié ou un flux SSE persistant obligerait à maintenir en plus un démon de sonde perpétuel en tâche de fond et un broker de messages tiers — soit des composants supplémentaires susceptibles de tomber en panne.
2. **Notre choix : Short Polling HTTP REST Stateless toutes les 2 secondes (`GET /api/microservices?probe=true`)** :
   - Toutes les **2 secondes**, le Wallboard Angular interroge l'API REST Laravel.
   - À chaque appel, Laravel sonde en direct les 5 conteneurs Docker (`ms-core` à `ms-salle` sur le réseau interne Docker en `< 15 ms`), met à jour MySQL si un conteneur s'est arrêté ou rallumé, et retourne le JSON consolidé.
   - Parallèlement, tout clic sur un bouton de simulation (`POST /api/microservices/{slug}/simulate`) met à jour immédiatement l'interface et le conteneur cible sans même attendre la fin des 2 secondes.
   - **Résultat** : Une alerte visuelle garantie en **moins de 2 secondes** (et immédiate lors d'une simulation UI), avec une architecture **100 % stateless, standard et immédiatement compréhensible**.

---

### 1.3 Modélisation de la Base de Données Relationnelle
Le schéma complet est documenté dans [`docs/SCHEMA_RELATIONNEL.md`](docs/SCHEMA_RELATIONNEL.md). Il s'articule autour de **3 tables normalisées (3NF)** générées via `php artisan make:model -m` :

- **`microservices`** : Contient la configuration et l'état instantané (`UP`, `DOWN`, `DEGRADED`, `is_simulated_down`, `is_critical`, `response_time_ms`, `uptime_percentage`, `last_status_change_at`) des 5 services (`core`, `mailing`, `carto`, `voirie`, `salle`).
- **`service_logs`** *(Relation `1:N` avec `microservices`)* : Journalise chaque transition d'état (`previous_status` $\rightarrow$ `new_status`), la source (`SIMULATION_UI`, `DOCKER_PROBE`) et calcule automatiquement la **durée d'indisponibilité en secondes (`downtime_duration_seconds`)** lors de la reconnexion d'un service.
- **`health_checks`** *(Relation `1:N` avec `microservices`)* : Historise les sondes HTTP actives (`http_status_code`, `latency_ms`, `is_healthy`) effectuées auprès des 5 conteneurs Docker.

---

### 1.4 Ergonomie "Wallboard / NOC TV" & Alerte Visuelle Rapide
Un écran de monitoring mural doit être lisible à **plusieurs mètres de distance** :
- **État Nominal (`OPERATIONAL`)** : Thème sombre reposant (ardoise/nuit), indicateurs LED verts émeraude pulsés, affichage clair des latences et du taux de disponibilité.
- **État d'Alerte Immédiate (`CRITICAL_ALERT` dès qu'un service est `DOWN`)** :
  - Apparition instantanée d'un **bandeau supérieur rouge clignotant haute visibilité** listant les services déconnectés et leur **chronomètre de panne en temps réel (`00m 15s`)**.
  - Halo lumineux rouge pulsé autour de l'écran (`@keyframes wallboardAlertGlow`) et de la carte du microservice en panne.
  - Si le service **`core`** (*interface client*) est déconnecté, affichage d'un avertissement spécifique d'**Impact Global Critique**.

---

## 🚀 2. Consignes pour Lancer le Projet

### 2.1 Démarrage clé-en-main avec Docker Desktop (Recommandé)
L'ensemble du projet (Base MySQL 8 + API Laravel + Frontend Angular + **les 5 conteneurs Docker des microservices**) se lance en **une seule commande** :

```bash
docker compose up -d --build
```

Lors du démarrage, le conteneur `wavre-backend-api` exécute automatiquement :
1. L'attente de disponibilité du serveur MySQL (`healthcheck`),
2. `php artisan migrate --seed --force` (création des tables et alimentation automatique des 5 microservices de la Ville de Wavre),
3. Le lancement du serveur d'API Laravel sur le port `8000`.

### 2.2 Accès aux Interfaces & URLs

| Service | URL d'accès | Description |
| :--- | :--- | :--- |
| 🖥️ **Wallboard de Monitoring (Angular)** | **[http://localhost:4200](http://localhost:4200)** | Écran principal de supervision (Polling 2s) + Boutons de simulation |
| 🔌 **API REST Laravel (JSON)** | **[http://localhost:8000/api/microservices](http://localhost:8000/api/microservices)** | État JSON des 5 microservices et sonde directe Docker |
| 📖 **Documentation Interactive Swagger UI** | **[http://localhost:8000/api/docs](http://localhost:8000/api/docs)** | Contrat OpenAPI 3.0.3 testable directement dans le navigateur |
| 🐳 **Sondes directes des 5 Microservices** | `http://localhost:8081/health` à `:8085/health` | `8081` (`core`), `8082` (`mailing`), `8083` (`carto`), `8084` (`voirie`), `8085` (`salle`) |

---

### 2.3 Exécution des Tests Automatisés (`php artisan test`) & Linters

#### Lancer la suite de tests PHPUnit via `php artisan` :
```bash
docker exec -it wavre-backend-api php artisan test
```

#### Lancer le linter PHP officiel **Laravel Pint (PSR-12)** :
```bash
docker exec -it wavre-backend-api ./vendor/bin/pint --test
```

#### Lancer le linter Frontend **ESLint** :
```bash
docker exec -it wavre-frontend-wallboard npm run lint
```

---

### 2.4 Restauration de la Base de Données (Seeder ou Dump SQL)

Le projet fournit **les deux méthodes** demandées dans l'énoncé :

1. **Via le Seeder Laravel (`DatabaseSeeder` / `MicroserviceSeeder`)** :
   ```bash
   docker exec -it wavre-backend-api php artisan migrate:fresh --seed
   ```
2. **Via le Dump SQL complet (`backend/database/dump.sql` incluant `CREATE TABLE` et `INSERT INTO`)** :
   ```bash
   docker exec -i wavre-mysql mysql -uwavre -psecret wavre_monitoring < backend/database/dump.sql
   ```

---

## ✨ 3. Liste Complète des Fonctionnalités

- ✅ **Supervision en temps réel des 5 microservices de la Ville de Wavre** (`core`, `mailing`, `carto`, `voirie`, `salle`) avec leurs rôles métiers exacts, ports, noms de conteneurs Docker, latences en millisecondes et taux de disponibilité (`Uptime %`).
- ✅ **Alerte visuelle immédiate et haute visibilité** (bandeau d'alerte critique clignotant, halo rouge autour de l'écran, compteur de durée d'indisponibilité en direct, détection d'impact critique lorsque `core` est déconnecté).
- ✅ **Boutons de simulation individuels et globaux** :
  - Simulation de **Déconnexion (`DOWN`)** sur chaque microservice (met à jour la BDD + configure le vrai conteneur Docker pour renvoyer `HTTP 503`).
  - Simulation de **Ralentissement (`DEGRADED`)** (injecte une latence élevée dans le conteneur Docker).
  - Simulation de **Reconnexion (`UP`)** (rétablit le conteneur en `HTTP 200`, lève l'alerte et calcule automatiquement la durée totale de la panne).
  - Boutons globaux : **"Tout rétablir (UP)"**, **"Simuler panne aléatoire (Chaos Test)"** et **"Sonder les conteneurs Docker (/ping)"**.
- ✅ **Détection automatique d'extinction/redémarrage de conteneur Docker** (`docker stop` / `docker start` dans Docker Desktop) grâce au Short Polling HTTP REST (2s) couplé à la sonde TCP/HTTP Laravel.
- ✅ **Journal d'Audit et d'Incidents Horodaté** (`service_logs`) affichant l'historique complet des déconnexions/reconnexions et les temps de coupure.
- ✅ **Contrat OpenAPI 3.0.3 (`docs/openapi.yaml`)** et interface **Swagger UI** intégrée (`/api/docs`).
- ✅ **Suite de tests automatisés PHPUnit** (`MicroserviceMonitoringTest`) et **Linters** (`Laravel Pint` PSR-12 + `ESLint`).

---

## 📂 4. Structure des Livrables du Projet

```text
epreuve_lavarel_wavre/
├── backend/                                # Code Backend PHP 8.4 / Laravel (commenté en français)
│   ├── app/
│   │   ├── Http/Controllers/Api/MicroserviceController.php
│   │   ├── Http/Requests/SimulateMicroserviceRequest.php
│   │   ├── Models/                         # Microservice.php, ServiceLog.php, HealthCheck.php
│   │   └── Services/HealthMonitorService.php
│   ├── database/
│   │   ├── migrations/                     # 3 tables relationnelles (microservices, service_logs, health_checks)
│   │   ├── seeders/                        # MicroserviceSeeder.php & DatabaseSeeder.php
│   │   └── dump.sql                        # Dump SQL complet (CREATE TABLE + INSERT INTO)
│   ├── routes/api.php                      # Routes REST + Swagger UI
│   ├── tests/Feature/MicroserviceMonitoringTest.php
│   └── pint.json                           # Configuration du linter Laravel Pint (PSR-12)
├── frontend/                               # Code Frontend Angular / TypeScript / CSS Wallboard
│   ├── src/app/
│   │   ├── models/microservice.model.ts
│   │   ├── services/monitoring.service.ts  # Service de supervision Short Polling HTTP REST (2s)
│   │   └── app.component.*                 # Interface Wallboard, Alertes Visuelles & Simulation
│   └── .eslintrc.json                      # Configuration du linter ESLint
├── docker/
│   ├── backend/                            # Dockerfile PHP 8.4 + script d'initialisation automatique
│   └── microservice-node/                  # Image des 5 vrais conteneurs Docker (core, mailing, carto, voirie, salle)
├── docs/
│   ├── SCHEMA_RELATIONNEL.md               # Schéma relationnel BDD (Mermaid ERD + dictionnaire)
│   ├── openapi.yaml                        # Contrat OpenAPI 3.0.3 complet
│   └── GUIDE_EXAMEN_ORAL.md                # Guide de préparation à l'examen oral (Justification Polling vs SSE/WS)
├── AGENTS.md & .agents/                    # Configuration des agents Antigravity (Backend & Frontend)
├── docker-compose.yml                      # Orchestration complète des 8 conteneurs Docker
└── README.md                               # Ce document (Réflexion, Lancement & Fonctionnalités)
```
