/**
 * ============================================================================
 * VILLE DE WAVRE â€” SERVICE DE SUPERVISION HTTP REST (MONITORING.SERVICE.TS)
 * ============================================================================
 * Architecture basÃ©e sur les Signals Angular 18 et un Short Polling HTTP REST
 * cadencÃ© toutes les 2 secondes (`2000 ms`) :
 *  1. Appelle `GET http://localhost:8000/api/microservices?probe=true` toutes
 *     les 2 secondes via `setInterval(() => this.fetchDashboard(), 2000)`.
 *  2. Met Ã  jour atomiquement le Signal `dashboardState` (`services`,
 *     `recentIncidents`, `pollingState`).
 *  3. Permet de simuler en direct la dÃ©connexion (`DOWN`), le ralentissement
 *     (`DEGRADED`) et la reconnexion (`UP`) de chacun des 5 microservices via
 *     `POST http://localhost:8000/api/microservices/{slug}/simulate`.
 * ============================================================================
 */
import { Injectable, OnDestroy, computed, inject, signal } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { catchError, of, tap } from 'rxjs';
import {
  DashboardState,
  IncidentLogItem,
  MicroserviceItem,
  MicroserviceSlug,
  ServiceStatus,
} from '../models/microservice.model';

@Injectable({
  providedIn: 'root',
})
export class RealtimeMonitoringService implements OnDestroy {
  private readonly http = inject(HttpClient);

  /** Point d'entrÃ©e REST du Backend Laravel 12 */
  private readonly apiBaseUrl = 'http://localhost:8000/api/microservices';

  /** Catalogue de rÃ©fÃ©rence des 5 microservices Docker de la Ville de Wavre */
  private readonly initialCatalog: MicroserviceItem[] = [
    {
      id: 1,
      slug: 'core',
      name: 'Microservice Core (Portail Citoyen)',
      container_name: 'wavre-ms-core',
      port: 8081,
      endpoint_url: 'http://ms-core:8081/health',
      role_description:
        "GÃ¨re l'interface client principale du guichet citoyen et orchestre les 4 microservices mÃ©tiers de la Ville de Wavre.",
      is_critical: true,
      status: 'UP',
      latency_ms: 11,
      uptime_percent: 99.98,
      down_since: null,
      last_checked_at: new Date().toISOString(),
      cpu_usage_percent: 14,
      memory_mb: 186,
    },
    {
      id: 2,
      slug: 'mailing',
      name: 'Microservice Mailing & Alertes',
      container_name: 'wavre-ms-mailing',
      port: 8082,
      endpoint_url: 'http://ms-mailing:8082/health',
      role_description:
        "GÃ¨re l'envoi transactionnel d'e-mails, accusÃ©s de rÃ©ception administratifs et notifications citoyennes.",
      is_critical: false,
      status: 'UP',
      latency_ms: 16,
      uptime_percent: 99.94,
      down_since: null,
      last_checked_at: new Date().toISOString(),
      cpu_usage_percent: 9,
      memory_mb: 128,
    },
    {
      id: 3,
      slug: 'carto',
      name: 'Microservice Cartographie (SIG)',
      container_name: 'wavre-ms-carto',
      port: 8083,
      endpoint_url: 'http://ms-carto:8083/health',
      role_description:
        'Fournit le SystÃ¨me dâ€™Information GÃ©ographique (SIG), le cadastre communal et le gÃ©ocodage des parcelles.',
      is_critical: false,
      status: 'UP',
      latency_ms: 23,
      uptime_percent: 99.91,
      down_since: null,
      last_checked_at: new Date().toISOString(),
      cpu_usage_percent: 22,
      memory_mb: 312,
    },
    {
      id: 4,
      slug: 'voirie',
      name: 'Microservice Voirie & MobilitÃ©',
      container_name: 'wavre-ms-voirie',
      port: 8084,
      endpoint_url: 'http://ms-voirie:8084/health',
      role_description:
        'GÃ¨re les arrÃªtÃ©s de police, les chantiers sur la voie publique et les autorisations de stationnement.',
      is_critical: false,
      status: 'UP',
      latency_ms: 18,
      uptime_percent: 99.95,
      down_since: null,
      last_checked_at: new Date().toISOString(),
      cpu_usage_percent: 12,
      memory_mb: 164,
    },
    {
      id: 5,
      slug: 'salle',
      name: 'Microservice Salles & Infrastructures',
      container_name: 'wavre-ms-salle',
      port: 8085,
      endpoint_url: 'http://ms-salle:8085/health',
      role_description:
        'GÃ¨re le planning et la rÃ©servation des salles communales, halls sportifs et matÃ©riel Ã©vÃ©nementiel.',
      is_critical: false,
      status: 'UP',
      latency_ms: 14,
      uptime_percent: 99.97,
      down_since: null,
      last_checked_at: new Date().toISOString(),
      cpu_usage_percent: 8,
      memory_mb: 142,
    },
  ];

  /**
   * Signal rÃ©actif principal (`dashboardState`) regroupant l'Ã©tat des 5 microservices,
   * le journal des incidents (`recentIncidents`) et l'Ã©tat du Short Polling (`pollingState`).
   */
  public readonly dashboardState = signal<DashboardState>({
    services: this.initialCatalog,
    recentIncidents: [
      {
        id: 'init-boot-rest',
        service_slug: 'core',
        service_name: 'Microservice Core (Portail Citoyen)',
        container_name: 'wavre-ms-core',
        previous_status: 'UP',
        new_status: 'UP',
        source: 'DOCKER_PROBE',
        reason:
          'Initialisation du Wallboard NOC Ville de Wavre â€” Supervision HTTP REST active (Sonde Docker toutes les 2s).',
        occurred_at: new Date().toISOString(),
        duration_seconds: null,
      },
    ],
    pollingState: {
      isPolling: true,
      intervalMs: 2000,
      lastCheckedAt: new Date().toISOString(),
      pollCount: 1,
      apiLatencyMs: 14,
    },
  });

  /** Signaux dÃ©rivÃ©s (computed) pour un accÃ¨s direct dans le composant Wallboard */
  public readonly servicesSignal = computed(() => this.dashboardState().services);
  public readonly recentIncidentsSignal = computed(
    () => this.dashboardState().recentIncidents
  );
  public readonly pollingStateSignal = computed(
    () => this.dashboardState().pollingState
  );

  /** Identifiant du timer `setInterval` de scrutation toutes les 2 secondes */
  private pollingIntervalId: ReturnType<typeof setInterval> | null = null;

  constructor() {
    // 1. Chargement initial immÃ©diat au dÃ©marrage
    this.fetchDashboard();

    // 2. Scrutation pÃ©riodique Short Polling HTTP REST toutes les 2 secondes (2000 ms)
    this.pollingIntervalId = setInterval(() => {
      this.fetchDashboard();
    }, 2000);
  }

  /**
   * Interroge l'endpoint `GET http://localhost:8000/api/microservices?probe=true`
   * et met Ã  jour le Signal `dashboardState` (`services`, `recentIncidents`, `pollingState`).
   */
  public fetchDashboard(): void {
    const startedAt = performance.now();

    this.http
      .get<unknown>(`${this.apiBaseUrl}?probe=true`)
      .pipe(
        tap((response: unknown) => {
          const measuredLatencyMs = Math.max(
            4,
            Math.round(performance.now() - startedAt)
          );
          this.applyDashboardPayload(response, measuredLatencyMs);
        }),
        catchError(() => {
          // RÃ©silience si le serveur backend est temporairement indisponible :
          // incrÃ©mente le compteur de sonde tout en conservant l'Ã©tat local.
          const current = this.dashboardState();
          this.dashboardState.set({
            ...current,
            pollingState: {
              ...current.pollingState,
              isPolling: true,
              lastCheckedAt: new Date().toISOString(),
              pollCount: current.pollingState.pollCount + 1,
            },
          });
          return of(null);
        })
      )
      .subscribe();
  }

  /**
   * ==========================================================================
   * SIMULATION DE DÃ‰CONNEXION / RALENTISSEMENT / RECONNEXION (EXIGENCE Ã‰NONCÃ‰)
   * ==========================================================================
   * Applique immÃ©diatement le changement d'Ã©tat et appelle
   * `POST http://localhost:8000/api/microservices/{slug}/simulate`.
   */
  public simulateServiceStatus(
    slug: MicroserviceSlug,
    targetStatus: ServiceStatus
  ): void {
    const nowIso = new Date().toISOString();
    const current = this.dashboardState();
    const targetService = current.services.find((item) => item.slug === slug);

    if (!targetService) {
      return;
    }

    const previousStatus = targetService.status;
    const simulatedLatency =
      targetStatus === 'DOWN'
        ? 0
        : targetStatus === 'DEGRADED'
          ? 485
          : Math.floor(Math.random() * 10) + 10;

    // Calcul de la durÃ©e exacte de la panne lors du retour Ã  UP
    let outageDurationSeconds: number | null = null;
    if (previousStatus === 'DOWN' && targetStatus !== 'DOWN' && targetService.down_since) {
      const downStartMs = new Date(targetService.down_since).getTime();
      outageDurationSeconds = Math.max(1, Math.round((Date.now() - downStartMs) / 1000));
    }

    const updatedDownSince =
      targetStatus === 'DOWN' ? targetService.down_since ?? nowIso : null;

    const updatedServices: MicroserviceItem[] = current.services.map((service) => {
      if (service.slug !== slug) {
        return service;
      }
      return {
        ...service,
        status: targetStatus,
        latency_ms: simulatedLatency,
        down_since: updatedDownSince,
        last_checked_at: nowIso,
        cpu_usage_percent:
          targetStatus === 'DOWN' ? 0 : targetStatus === 'DEGRADED' ? 89 : 14,
      };
    });

    const reasonText =
      targetStatus === 'DOWN'
        ? `Simulation manuelle d'arrÃªt brutal du conteneur Docker (${targetService.container_name} injoignable sur le port :${targetService.port}).`
        : targetStatus === 'DEGRADED'
          ? `Simulation de saturation CPU / latence rÃ©seau Ã©levÃ©e (${simulatedLatency} ms) sur ${targetService.container_name}.`
          : `RÃ©tablissement et reconnexion du conteneur ${targetService.container_name} (HealthCheck HTTP 200 OK en ${simulatedLatency} ms).`;

    const newIncident: IncidentLogItem = {
      id: `sim-${Date.now()}-${slug}`,
      service_slug: slug,
      service_name: targetService.name,
      container_name: targetService.container_name,
      previous_status: previousStatus,
      new_status: targetStatus,
      source: 'SIMULATION_UI',
      reason: reasonText,
      occurred_at: nowIso,
      duration_seconds: outageDurationSeconds,
    };

    this.dashboardState.set({
      services: updatedServices,
      recentIncidents: [newIncident, ...current.recentIncidents.slice(0, 24)],
      pollingState: {
        ...current.pollingState,
        isPolling: true,
        lastCheckedAt: nowIso,
        pollCount: current.pollingState.pollCount + 1,
      },
    });

    const requestStart = performance.now();
    this.http
      .post<unknown>(`${this.apiBaseUrl}/${slug}/simulate`, {
        status: targetStatus,
        latency_ms: simulatedLatency,
        reason: reasonText,
      })
      .pipe(
        tap((response: unknown) => {
          const latencyMs = Math.max(4, Math.round(performance.now() - requestStart));
          this.applyDashboardPayload(response, latencyMs);
        }),
        catchError(() => of(null))
      )
      .subscribe();
  }

  /**
   * Bouton d'urgence du bandeau critique :
   * "RÃ©tablir tous les services immÃ©diatement"
   * Remet instantanÃ©ment tous les conteneurs `DOWN` ou `DEGRADED` Ã  l'Ã©tat `UP`.
   */
  public recoverAllServices(): void {
    const nowIso = new Date().toISOString();
    const current = this.dashboardState();
    const newIncidents: IncidentLogItem[] = [];

    const restoredServices: MicroserviceItem[] = current.services.map((service) => {
      if (service.status === 'UP') {
        return service;
      }

      let durationSec: number | null = null;
      if (service.status === 'DOWN' && service.down_since) {
        durationSec = Math.max(
          1,
          Math.round((Date.now() - new Date(service.down_since).getTime()) / 1000)
        );
      }

      const healthyLatency = Math.floor(Math.random() * 9) + 11;

      newIncidents.push({
        id: `rec-${Date.now()}-${service.slug}`,
        service_slug: service.slug,
        service_name: service.name,
        container_name: service.container_name,
        previous_status: service.status,
        new_status: 'UP',
        source: 'SYSTEM_RECOVERY',
        reason: `RÃ©tablissement global d'urgence dÃ©clenchÃ© depuis le Wallboard NOC (${service.container_name} de nouveau opÃ©rationnel).`,
        occurred_at: nowIso,
        duration_seconds: durationSec,
      });

      this.http
        .post<unknown>(`${this.apiBaseUrl}/${service.slug}/simulate`, {
          status: 'UP',
          latency_ms: healthyLatency,
          reason: 'RÃ©tablissement global depuis le Wallboard NOC',
        })
        .pipe(catchError(() => of(null)))
        .subscribe();

      return {
        ...service,
        status: 'UP',
        latency_ms: healthyLatency,
        down_since: null,
        last_checked_at: nowIso,
        cpu_usage_percent: 12,
      };
    });

    this.dashboardState.set({
      services: restoredServices,
      recentIncidents:
        newIncidents.length > 0
          ? [...newIncidents, ...current.recentIncidents.slice(0, 20)]
          : current.recentIncidents,
      pollingState: {
        ...current.pollingState,
        isPolling: true,
        lastCheckedAt: nowIso,
        pollCount: current.pollingState.pollCount + 1,
      },
    });

    const requestStart = performance.now();
    this.http
      .post<unknown>(`${this.apiBaseUrl}/recover-all`, {})
      .pipe(
        tap((response: unknown) => {
          const latencyMs = Math.max(4, Math.round(performance.now() - requestStart));
          this.applyDashboardPayload(response, latencyMs);
        }),
        catchError(() => of(null))
      )
      .subscribe();
  }

  /**
   * Applique la rÃ©ponse JSON reÃ§ue du Backend Laravel au Signal `dashboardState`.
   */
  private applyDashboardPayload(response: unknown, measuredLatencyMs: number): void {
    if (!response || typeof response !== 'object') {
      return;
    }

    const current = this.dashboardState();
    const record = response as Record<string, unknown>;
    const rawServices =
      (Array.isArray(response) ? response : null) ??
      (Array.isArray(record['data']) ? record['data'] : null) ??
      (Array.isArray(record['services']) ? record['services'] : null) ??
      (Array.isArray(record['microservices']) ? record['microservices'] : null);

    let nextServices = current.services;

    if (rawServices && rawServices.length > 0) {
      nextServices = current.services.map((catalogItem) => {
        const match = (rawServices as Record<string, unknown>[]).find(
          (apiItem) =>
            apiItem['slug'] === catalogItem.slug ||
            apiItem['container_name'] === catalogItem.container_name
        );

        if (!match) {
          return catalogItem;
        }

        const apiStatus = String(
          match['status'] ?? catalogItem.status
        ).toUpperCase() as ServiceStatus;
        const validStatus: ServiceStatus =
          apiStatus === 'DOWN' || apiStatus === 'DEGRADED' || apiStatus === 'UP'
            ? apiStatus
            : catalogItem.status;

        const downSince =
          validStatus === 'DOWN'
            ? typeof match['down_since'] === 'string'
              ? match['down_since']
              : catalogItem.down_since ?? new Date().toISOString()
            : null;

        return {
          ...catalogItem,
          status: validStatus,
          endpoint_url: typeof match['health_url'] === 'string' ? match['health_url'] : catalogItem.endpoint_url,
          latency_ms:
            typeof match['latency_ms'] === 'number'
              ? match['latency_ms']
              : typeof match['response_time_ms'] === 'number'
                ? match['response_time_ms']
                : catalogItem.latency_ms,
          uptime_percent:
            typeof match['uptime_percent'] === 'number'
              ? match['uptime_percent']
              : catalogItem.uptime_percent,
          down_since: downSince,
          last_checked_at:
            typeof match['last_checked_at'] === 'string'
              ? match['last_checked_at']
              : new Date().toISOString(),
        };
      });
    }

    let nextIncidents = current.recentIncidents;
    const rawIncidents = record['recent_incidents'];
    if (Array.isArray(rawIncidents) && rawIncidents.length > 0) {
      nextIncidents = (rawIncidents as Record<string, unknown>[]).map((inc, index) => ({
        id: String(inc['id'] ?? `rest-inc-${index}`),
        service_slug: (inc['service_slug'] as MicroserviceSlug) ?? 'core',
        service_name: String(inc['service_name'] ?? 'Microservice Wavre'),
        container_name: String(inc['container_name'] ?? 'wavre-ms-container'),
        previous_status: (inc['previous_status'] as ServiceStatus) ?? 'UP',
        new_status: (inc['new_status'] as ServiceStatus) ?? 'DOWN',
        source:
          (inc['source'] as 'SIMULATION_UI' | 'DOCKER_PROBE' | 'SYSTEM_RECOVERY') ??
          'DOCKER_PROBE',
        reason: String(inc['reason'] ?? 'Changement dâ€™Ã©tat dÃ©tectÃ© par la sonde Docker'),
        occurred_at: String(inc['occurred_at'] ?? new Date().toISOString()),
        duration_seconds:
          typeof inc['duration_seconds'] === 'number' ? inc['duration_seconds'] : null,
      }));
    }

    this.dashboardState.set({
      services: nextServices,
      recentIncidents: nextIncidents,
      pollingState: {
        isPolling: true,
        intervalMs: 2000,
        lastCheckedAt: new Date().toISOString(),
        pollCount: current.pollingState.pollCount + 1,
        apiLatencyMs: measuredLatencyMs,
      },
    });
  }

  public ngOnDestroy(): void {
    if (this.pollingIntervalId) {
      clearInterval(this.pollingIntervalId);
      this.pollingIntervalId = null;
    }
  }
}
