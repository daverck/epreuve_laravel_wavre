# Schéma Relationnel de la Base de Données — Monitoring Microservices (Ville de Wavre)

Ce document présente l'architecture de la base de données relationnelle (**MySQL 8.0 / MariaDB / SQLite**) utilisée par le backend **Laravel** pour superviser les 5 microservices conteneurisés de la Ville de Wavre (`core`, `mailing`, `carto`, `voirie`, `salle`).

---

## 1. Diagramme Entité-Association (ERD)

```mermaid
erDiagram
    MICROSERVICES ||--o{ SERVICE_LOGS : "historise (1:N)"
    MICROSERVICES ||--o{ HEALTH_CHECKS : "mesure (1:N)"

    MICROSERVICES {
        bigint_unsigned id PK "Clé primaire auto-incrémentée"
        varchar_50 slug UK "Identifiant métier unique : core, mailing, carto, voirie, salle"
        varchar_120 name "Libellé complet du microservice"
        text description "Description fonctionnelle (rôle métier à la Ville de Wavre)"
        varchar_100 container_name "Nom du conteneur Docker (ex: wavre-ms-core)"
        varchar_255 health_url "URL HTTP interne de sonde (ex: http://ms-core:8081/health)"
        smallint_unsigned port "Port TCP exposé (8081 à 8085)"
        enum status "État courant : UP | DOWN | DEGRADED (défaut: UP)"
        boolean is_simulated_down "Indique si la panne actuelle provient d'une simulation manuelle"
        boolean is_critical "True pour 'core' (l'interface client dépend de ce service)"
        int_unsigned response_time_ms "Dernier temps de réponse mesuré en millisecondes"
        decimal_5_2 uptime_percentage "Taux de disponibilité calculé (ex: 99.95%)"
        timestamp last_checked_at "Date et heure de la dernière sonde HTTP"
        timestamp last_status_change_at "Date et heure du dernier changement d'état (calcul du downtime)"
        timestamp created_at
        timestamp updated_at
    }

    SERVICE_LOGS {
        bigint_unsigned id PK "Clé primaire auto-incrémentée"
        bigint_unsigned microservice_id FK "Clé étrangère vers microservices.id (ON DELETE CASCADE)"
        enum previous_status "Ancien état : UP | DOWN | DEGRADED"
        enum new_status "Nouvel état : UP | DOWN | DEGRADED"
        enum trigger_source "Origine : SIMULATION_UI | DOCKER_PROBE | SYSTEM_INIT"
        varchar_255 message "Explication lisible (ex: Déconnexion simulée par l'opérateur)"
        int_unsigned downtime_duration_seconds "Durée totale de l'incident en secondes (lors du retour à UP)"
        timestamp created_at "Horodatage précis de l'événement"
        timestamp updated_at
    }

    HEALTH_CHECKS {
        bigint_unsigned id PK "Clé primaire auto-incrémentée"
        bigint_unsigned microservice_id FK "Clé étrangère vers microservices.id (ON DELETE CASCADE)"
        smallint_unsigned http_status_code "Code HTTP retourné par le conteneur (200, 503, 500)"
        int_unsigned latency_ms "Temps d'exécution de la requête HTTP en ms"
        boolean is_healthy "True si le conteneur répond normalement"
        timestamp checked_at "Date et heure de la mesure"
    }
```

---

## 2. Justification des Choix de Modélisation (Pour l'Examen Oral)

### 2.1 Normalisation (3ème Forme Normale - 3NF)
1. **Table `microservices` (État courant & Configuration)** :
   - Stocke l'état instantané (`status`, `response_time_ms`, `last_status_change_at`) des 5 microservices (`core`, `mailing`, `carto`, `voirie`, `salle`).
   - Lire les 5 lignes de la table `microservices` est une opération en $O(1)$ ultra-rapide (< 1 ms) sans avoir besoin de faire un `GROUP BY` coûteux sur des millions de lignes d'historique.
2. **Table `service_logs` (Journal d'Audit & Incidents — Relation `1:N`)** :
   - Enregistre uniquement les **transitions d'état** (`UP -> DOWN`, `DOWN -> UP`, `UP -> DEGRADED`) et calcule exactement combien de secondes un service est resté hors-ligne (`downtime_duration_seconds`) au moment où il se reconnecte.
3. **Table `health_checks` (Télémétrie & Sondes HTTP — Relation `1:N`)** :
   - Sépare les mesures périodiques de latence (`health_checks`) et les incidents/changements d'état (`service_logs`) afin de ne pas polluer le journal d'incidents avec de simples pings `200 OK`.

### 2.2 Intégrité Référentielle & Indexation
- **Clés étrangères (`FOREIGN KEY`)** avec contrainte `ON DELETE CASCADE`.
- **Index d'unicité (`UNIQUE`)** sur `microservices.slug`.
- **Index composite** sur `service_logs(microservice_id, created_at)`.