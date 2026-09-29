/**
 * ============================================================================
 * VILLE DE WAVRE — MODÈLES DE DONNÉES DU WALLBOARD DE SUPERVISION (TYPESCRIPT)
 * ============================================================================
 * Ce fichier définit les contrats de données (Interfaces & Types stricts)
 * partagés entre le Backend Laravel 12 (API REST & Diffusion WebSocket) et
 * le Frontend Angular 18 (Architecture réactive à base de Signals).
 *
 * Architecture supervisée : 5 Microservices Dockerisés :
 *  1. `core`    (Critique) : Interface citoyenne principale & orchestration
 *  2. `mailing`            : Envoi transactionnel de mails et notifications
 *  3. `carto`              : Système d'Information Géographique (SIG / GIS)
 *  4. `voirie`             : Arrêtés de police et autorisations de stationnement
 *  5. `salle`              : Réservation des salles et infrastructures communales
 * ============================================================================
 */

/**
 * Identifiants uniques (slugs) des 5 microservices de l'écosystème Ville de Wavre.
 */
export type MicroserviceSlug = 'core' | 'mailing' | 'carto' | 'voirie' | 'salle';

/**
 * États opérationnels possibles d'un conteneur Docker supervisé :
 * - `UP`       : Opérationnel (LED Verte émeraude)
 * - `DEGRADED` : Ralentissement / Latence élevée > 350 ms (LED Ambre)
 * - `DOWN`     : Déconnecté / Conteneur arrêté ou injoignable (LED Rouge clignotante)
 */
export type ServiceStatus = 'UP' | 'DEGRADED' | 'DOWN';

/**
 * Représentation complète d'un microservice affiché sur le Wallboard TV.
 */
export interface MicroserviceItem {
  /** Identifiant numérique en base de données */
  id: number;
  /** Identifiant métier court (`core`, `mailing`, `carto`, `voirie`, `salle`) */
  slug: MicroserviceSlug;
  /** Nom d'affichage officiel sur l'écran mural */
  name: string;
  /** Nom exact du conteneur Docker supervisé (ex: `wavre-ms-voirie`) */
  container_name: string;
  /** Port TCP exposé par le conteneur (ex: `8084`) */
  port: number;
  /** URL de la sonde HealthCheck interne */
  endpoint_url: string;
  /** Rôle métier exact au sein de l'administration communale de Wavre */
  role_description: string;
  /** Indique si la chute de ce service coupe l'interface citoyenne globale (`true` pour `core`) */
  is_critical: boolean;
  /** Statut actuel du microservice (`UP`, `DEGRADED`, `DOWN`) */
  status: ServiceStatus;
  /** Temps de réponse mesuré lors du dernier HealthCheck (en millisecondes) */
  latency_ms: number;
  /** Taux de disponibilité SLA sur 30 jours (ex: `99.95`) */
  uptime_percent: number;
  /** Horodatage ISO du début de la panne si `status === 'DOWN'` (sinon `null`) */
  down_since: string | null;
  /** Horodatage ISO de la dernière vérification par la sonde Laravel */
  last_checked_at: string;
  /** Charge CPU estimée du conteneur Docker (%) */
  cpu_usage_percent: number;
  /** Consommation RAM du conteneur Docker (Mo) */
  memory_mb: number;
}

/**
 * Entrée du journal des incidents en direct (`recent_incidents`).
 * Permet de tracer chaque transition d'état (`UP -> DOWN`, `DOWN -> UP`, etc.)
 * ainsi que la durée exacte de l'interruption de service.
 */
export interface IncidentLogItem {
  /** Identifiant unique de l'événement */
  id: string;
  /** Slug du microservice concerné */
  service_slug: MicroserviceSlug;
  /** Nom lisible du microservice */
  service_name: string;
  /** Nom du conteneur Docker impacté */
  container_name: string;
  /** Statut avant la transition */
  previous_status: ServiceStatus;
  /** Nouveau statut après la transition */
  new_status: ServiceStatus;
  /** Origine du changement d'état (`SIMULATION_UI`, `DOCKER_PROBE`, `SYSTEM_RECOVERY`) */
  source: 'SIMULATION_UI' | 'DOCKER_PROBE' | 'SYSTEM_RECOVERY';
  /** Message explicatif détaillant la cause ou l'action opérateur */
  reason: string;
  /** Horodatage ISO de l'événement */
  occurred_at: string;
  /** Durée totale de la coupure en secondes (renseignée lors du retour à `UP`) */
  duration_seconds: number | null;
}

/**
 * État de connexion WebSocket Temps Réel (`ws://localhost:6001`) affiché dans
 * l'en-tête du Wallboard NOC de la Ville de Wavre.
 */
export interface WebSocketConnectionState {
  /** Indique si le canal WebSocket Full-Duplex est actif et connecté */
  wsConnected: boolean;
  /** Point de terminaison WebSocket (ex: `ws://localhost:6001`) */
  wsEndpoint: string;
  /** Latence mesurée du dernier événement WebSocket reçu (en ms, typiquement < 5 ms) */
  lastEventLatencyMs: number;
  /** Nombre total d'événements `MicroserviceStatusUpdated` reçus via WebSocket */
  wsEventsCount: number;
  /** Horodatage ISO de la dernière trame WebSocket reçue */
  lastEventAt: string;
  /** Libellé lisible de l'état du socket (ex: `Connecté (Full-Duplex < 5 ms)`) */
  statusText: string;
}

/**
 * État global réactif du Wallboard (stocké dans un Signal Angular `dashboardState`).
 */
export interface DashboardState {
  /** Les 5 microservices supervisés de la Ville de Wavre */
  services: MicroserviceItem[];
  /** Le fil chronologique des incidents en direct */
  recentIncidents: IncidentLogItem[];
  /** L'état de la connexion WebSocket Temps Réel */
  wsState: WebSocketConnectionState;
}

/**
 * Charge utile (Payload) diffusée par l'événement Laravel `MicroserviceStatusUpdated`
 * sur le canal WebSocket (`ws://localhost:6001`).
 */
export interface WebSocketBroadcastPayload {
  event?: string;
  service?: Partial<MicroserviceItem> & { slug?: MicroserviceSlug };
  services?: MicroserviceItem[];
  recent_incidents?: IncidentLogItem[];
  timestamp?: string;
}
