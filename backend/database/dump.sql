-- =============================================================================
-- DUMP SQL COMPLET — SUPERVISION DES MICROSERVICES (VILLE DE WAVRE)
-- Compatible MySQL 8.0+ / MariaDB 10.6+
-- Contient la structure DDL (CREATE TABLE) et les données initiales (INSERT INTO)
-- pour les 5 microservices : core, mailing, carto, voirie, salle.
-- =============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS `wavre_monitoring`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `wavre_monitoring`;

-- -----------------------------------------------------------------------------
-- 1. Table `microservices` : État courant et configuration des 5 conteneurs
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `health_checks`;
DROP TABLE IF EXISTS `service_logs`;
DROP TABLE IF EXISTS `microservices`;

CREATE TABLE `microservices` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug` VARCHAR(50) NOT NULL COMMENT 'Identifiant métier unique (core, mailing, carto, voirie, salle)',
  `name` VARCHAR(120) NOT NULL COMMENT 'Nom complet du microservice',
  `description` TEXT NOT NULL COMMENT 'Rôle fonctionnel au sein de la Ville de Wavre',
  `container_name` VARCHAR(100) NOT NULL COMMENT 'Nom du conteneur Docker supervisé',
  `health_url` VARCHAR(255) NOT NULL COMMENT 'URL HTTP interne de sonde (/health)',
  `port` SMALLINT UNSIGNED NOT NULL COMMENT 'Port TCP exposé (8081 à 8085)',
  `status` ENUM('UP', 'DOWN', 'DEGRADED') NOT NULL DEFAULT 'UP' COMMENT 'Statut temps réel',
  `is_simulated_down` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Indique si la panne est simulée manuellement',
  `is_critical` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 pour core (bloque l interface client si DOWN)',
  `response_time_ms` INT UNSIGNED NOT NULL DEFAULT 12 COMMENT 'Dernière latence HTTP mesurée en ms',
  `uptime_percentage` DECIMAL(5,2) NOT NULL DEFAULT 100.00 COMMENT 'Taux de disponibilité en pourcentage',
  `last_checked_at` TIMESTAMP NULL DEFAULT NULL COMMENT 'Horodatage de la dernière sonde HTTP',
  `last_status_change_at` TIMESTAMP NULL DEFAULT NULL COMMENT 'Horodatage du dernier changement d état',
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `microservices_slug_unique` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 2. Table `service_logs` : Journal d'audit des déconnexions et reconnexions
-- -----------------------------------------------------------------------------
CREATE TABLE `service_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `microservice_id` BIGINT UNSIGNED NOT NULL,
  `previous_status` ENUM('UP', 'DOWN', 'DEGRADED') NOT NULL,
  `new_status` ENUM('UP', 'DOWN', 'DEGRADED') NOT NULL,
  `trigger_source` ENUM('SIMULATION_UI', 'DOCKER_PROBE', 'SYSTEM_INIT') NOT NULL DEFAULT 'SIMULATION_UI',
  `message` VARCHAR(255) NOT NULL,
  `downtime_duration_seconds` INT UNSIGNED NULL DEFAULT NULL COMMENT 'Durée totale de la panne en secondes lors du retour à UP',
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `service_logs_microservice_id_created_at_index` (`microservice_id`, `created_at`),
  CONSTRAINT `service_logs_microservice_id_foreign`
    FOREIGN KEY (`microservice_id`) REFERENCES `microservices` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 3. Table `health_checks` : Historique des sondes de latence HTTP
-- -----------------------------------------------------------------------------
CREATE TABLE `health_checks` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `microservice_id` BIGINT UNSIGNED NOT NULL,
  `http_status_code` SMALLINT UNSIGNED NOT NULL,
  `latency_ms` INT UNSIGNED NOT NULL,
  `is_healthy` TINYINT(1) NOT NULL,
  `checked_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `health_checks_microservice_id_checked_at_index` (`microservice_id`, `checked_at`),
  CONSTRAINT `health_checks_microservice_id_foreign`
    FOREIGN KEY (`microservice_id`) REFERENCES `microservices` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 4. Insertion des 5 Microservices de la Ville de Wavre
-- -----------------------------------------------------------------------------
INSERT INTO `microservices` (
  `id`, `slug`, `name`, `description`, `container_name`, `health_url`, `port`,
  `status`, `is_simulated_down`, `is_critical`, `response_time_ms`, `uptime_percentage`,
  `last_checked_at`, `last_status_change_at`, `created_at`, `updated_at`
) VALUES
(1, 'core', 'Core (Interface Client)', 'Sert l\'interface client', 'wavre-ms-core', 'http://ms-core:8081/health', 8081, 'UP', 0, 1, 11, 99.99, NOW(), DATE_SUB(NOW(), INTERVAL 4 HOUR), NOW(), NOW()),
(2, 'mailing', 'Mailing (Notifications)', 'Envoie les mails pour les différents processus', 'wavre-ms-mailing', 'http://ms-mailing:8082/health', 8082, 'UP', 0, 0, 18, 99.95, NOW(), DATE_SUB(NOW(), INTERVAL 4 HOUR), NOW(), NOW()),
(3, 'carto', 'Carto (Données SIG)', 'Gère le CRUD des données cartographiques', 'wavre-ms-carto', 'http://ms-carto:8083/health', 8083, 'UP', 0, 0, 24, 99.90, NOW(), DATE_SUB(NOW(), INTERVAL 4 HOUR), NOW(), NOW()),
(4, 'voirie', 'Voirie (Police & Stationnement)', 'Gère les arrêtés de police et les autorisations de stationnement', 'wavre-ms-voirie', 'http://ms-voirie:8084/health', 8084, 'UP', 0, 0, 15, 99.94, NOW(), DATE_SUB(NOW(), INTERVAL 4 HOUR), NOW(), NOW()),
(5, 'salle', 'Salle (Locations Communales)', 'Gère les locations de salles', 'wavre-ms-salle', 'http://ms-salle:8085/health', 8085, 'UP', 0, 0, 14, 99.97, NOW(), DATE_SUB(NOW(), INTERVAL 4 HOUR), NOW(), NOW());

-- -----------------------------------------------------------------------------
-- 5. Insertion des journaux initiaux (`service_logs`)
-- -----------------------------------------------------------------------------
INSERT INTO `service_logs` (
  `microservice_id`, `previous_status`, `new_status`, `trigger_source`, `message`, `downtime_duration_seconds`, `created_at`, `updated_at`
) VALUES
(1, 'UP', 'UP', 'SYSTEM_INIT', 'Initialisation du conteneur [wavre-ms-core] sur le port 8081 (statut nominal UP).', NULL, NOW(), NOW()),
(2, 'UP', 'UP', 'SYSTEM_INIT', 'Initialisation du conteneur [wavre-ms-mailing] sur le port 8082 (statut nominal UP).', NULL, NOW(), NOW()),
(3, 'UP', 'UP', 'SYSTEM_INIT', 'Initialisation du conteneur [wavre-ms-carto] sur le port 8083 (statut nominal UP).', NULL, NOW(), NOW()),
(4, 'UP', 'UP', 'SYSTEM_INIT', 'Initialisation du conteneur [wavre-ms-voirie] sur le port 8084 (statut nominal UP).', NULL, NOW(), NOW()),
(5, 'UP', 'UP', 'SYSTEM_INIT', 'Initialisation du conteneur [wavre-ms-salle] sur le port 8085 (statut nominal UP).', NULL, NOW(), NOW());

-- -----------------------------------------------------------------------------
-- 6. Insertion des sondes de santé initiales (`health_checks`)
-- -----------------------------------------------------------------------------
INSERT INTO `health_checks` (
  `microservice_id`, `http_status_code`, `latency_ms`, `is_healthy`, `checked_at`
) VALUES
(1, 200, 11, 1, NOW()),
(2, 200, 18, 1, NOW()),
(3, 200, 24, 1, NOW()),
(4, 200, 15, 1, NOW()),
(5, 200, 14, 1, NOW());

SET FOREIGN_KEY_CHECKS = 1;
