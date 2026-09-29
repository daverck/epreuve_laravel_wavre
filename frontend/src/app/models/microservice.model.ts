/**
 * ============================================================================
 * VILLE DE WAVRE — MODÈLES DE DONNÉES DU WALLBOARD DE SUPERVISION (TYPESCRIPT)
 * ============================================================================
 * Ce fichier définit les contrats de données (Interfaces & Types stricts)
 * partagés entre le Backend Laravel 12 (API REST / SSE / WebSockets) et
 * le Frontend Angular 18.
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
 * Les 3 modes de transport Temps Réel implémentés pour répondre à la question
 * théorique et pratique de l'examen écrit de la Ville de Wavre :
 * - `WEBSOCKET` : Full-Duplex TCP (`ws://localhost:6001`) — Latence < 10 ms (Recommandé)
 * - `SSE`       : Server-Sent Events (`text/event-stream`) — Push HTTP unidirectionnel
 * - `POLLING`   : Short Polling HTTP (`GET /api/microservices` toutes les 2s)
 */
export type RealtimeTransportMode = 'WEBSOCKET' | 'SSE' | 'POLLING';

/**
 * État technique du canal de transport temps réel actif.
 */
export type TransportConnectionState =
  | 'CONNECTE'
  | 'CONNEXION_EN_COURS'
  | 'REPLI_HYBRIDE_ACTIF'
  | 'DECONNECTE';

/**
 * Représentation complète d'un microservice affiché sur le Wallboard TV.
 */
export interface MicroserviceItem {
  /** Identifiant numérique ou UUID en base de données */
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
  /** Origine du changement d'état (`SIMULATION_UI` ou `DOCKER_PROBE`) */
  source: 'SIMULATION_UI' | 'DOCKER_PROBE' | 'SYSTEM_RECOVERY';
  /** Message explicatif détaillant la cause ou l'action opérateur */
  reason: string;
  /** Horodatage ISO de l'événement */
  occurred_at: string;
  /** Durée totale de la coupure en secondes (renseignée lors du retour à `UP`) */
  duration_seconds: number | null;
}

/**
 * Métriques en temps réel du protocole de communication actif.
 * Affichées dans le bandeau pédagogique en haut du Wallboard pour comparer
 * empiriquement WebSocket vs SSE vs Short Polling devant le jury.
 */
export interface RealtimeProtocolMetrics {
  /** Mode de transport actuellement sélectionné par l'opérateur */
  activeMode: RealtimeTransportMode;
  /** État de la connexion au serveur temps réel */
  connectionState: TransportConnectionState;
  /** Nombre total de trames / événements reçus dans le mode actif */
  eventsReceivedCount: number;
  /** Latence mesurée du dernier message temps réel (en ms) */
  lastProtocolLatencyMs: number;
  /** Horodatage ISO de la dernière trame reçue */
  lastEventTimestamp: string;
  /** URL du point de terminaison réseau utilisé par le protocole actif */
  endpointDisplay: string;
  /** Description courte du comportement technique en cours */
  statusDetail: string;
}

/**
 * Structure comparative pour le panneau pédagogique "Mémo Examen Oral :
 * Pourquoi WebSockets vs SSE vs Polling ?".
 */
export interface ProtocolComparisonRow {
  protocol: RealtimeTransportMode;
  badge: string;
  title: string;
  direction: string;
  averageLatency: string;
  networkOverhead: string;
  reconnectBehavior: string;
  wavreVerdict: string;
  isRecommended: boolean;
}

/**
 * Charge utile (Payload) diffusée par l'événement Laravel `MicroserviceStatusUpdated`
 * sur le canal WebSocket (`ws://localhost:6001`) ou le flux SSE (`/api/microservices/stream`).
 */
export interface RealtimeBroadcastPayload {
  event?: string;
  service?: Partial<MicroserviceItem> & { slug?: MicroserviceSlug };
  services?: MicroserviceItem[];
  recent_incidents?: IncidentLogItem[];
  timestamp?: string;
}
