/**
 * ============================================================================
 * VILLE DE WAVRE — COMPOSANT PRINCIPAL DU WALLBOARD NOC (APP.COMPONENT.TS)
 * ============================================================================
 * Composant Standalone Angular 18 pilotant l'écran mural (TV Wallboard) de
 * supervision des 5 microservices Docker (`core`, `mailing`, `carto`, `voirie`, `salle`).
 *
 * Fonctionnalités clés pour le Jury de l'Épreuve Technique :
 *  1. Détection visuelle critique immédiate (Bandeau rouge clignotant + Halo TV
 *     + Chronomètre de panne en direct à la seconde près `00m 42s`).
 *  2. Mention spéciale si le microservice `core` (Portail Citoyen) tombe à `DOWN`.
 *  3. Sélecteur interactif Temps Réel (`WEBSOCKET` ⚡ vs `SSE` 📡 vs `POLLING` 🔄)
 *     et panneau dépliable "Mémo Examen Oral : Pourquoi WebSockets vs SSE vs Polling ?".
 *  4. Boutons de simulation directe sur chaque carte microservice :
 *     🔴 Simuler Déconnexion (DOWN) | 🟠 Simuler Ralentissement (DEGRADED) | 🟢 Simuler Reconnexion (UP).
 * ============================================================================
 */
import { CommonModule } from '@angular/common';
import { Component, OnDestroy, OnInit, inject } from '@angular/core';
import { Subscription, interval } from 'rxjs';
import {
  IncidentLogItem,
  MicroserviceItem,
  MicroserviceSlug,
  ProtocolComparisonRow,
  RealtimeProtocolMetrics,
  RealtimeTransportMode,
  ServiceStatus,
} from './models/microservice.model';
import { RealtimeMonitoringService } from './services/monitoring.service';

@Component({
  selector: 'app-root',
  standalone: true,
  imports: [CommonModule],
  templateUrl: './app.component.html',
  styleUrls: ['./app.component.css'],
})
export class AppComponent implements OnInit, OnDestroy {
  private readonly monitoringService = inject(RealtimeMonitoringService);

  /** Liste réactive des 5 microservices de la Ville de Wavre */
  public microservices: MicroserviceItem[] = [];

  /** Journal chronologique des incidents en direct (`recent_incidents`) */
  public recentIncidents: IncidentLogItem[] = [];

  /** Métriques en direct du protocole Temps Réel actif (WebSocket / SSE / Polling) */
  public protocolMetrics: RealtimeProtocolMetrics = {
    activeMode: 'WEBSOCKET',
    connectionState: 'CONNECTE',
    eventsReceivedCount: 1,
    lastProtocolLatencyMs: 5,
    lastEventTimestamp: new Date().toISOString(),
    endpointDisplay: 'ws://localhost:6001 (Canal: monitoring.microservices)',
    statusDetail: 'Canal Full-Duplex WebSocket actif (< 10 ms).',
  };

  /** Tableau comparatif pour le Mémo Examen Oral */
  public readonly protocolComparisonRows: ProtocolComparisonRow[] =
    this.monitoringService.getProtocolComparisonTable();

  /** Contrôle l'ouverture du panneau pédagogique "Mémo Examen Oral" */
  public isExamMemoExpanded = false;

  /** Horloge murale NOC temps réel (mise à jour chaque seconde) */
  public currentWallboardClock = '';

  /** Timestamp courant (ms) rafraîchi chaque seconde pour les chronomètres de panne */
  public nowTimestampMs: number = Date.now();

  private readonly subscriptions = new Subscription();

  public ngOnInit(): void {
    this.updateClockDisplay();

    // Abonnement à la liste des 5 microservices
    this.subscriptions.add(
      this.monitoringService.microservices$.subscribe((services) => {
        this.microservices = services;
      })
    );

    // Abonnement au journal des incidents en direct
    this.subscriptions.add(
      this.monitoringService.incidents$.subscribe((incidents) => {
        this.recentIncidents = incidents;
      })
    );

    // Abonnement aux métriques du protocole Temps Réel
    this.subscriptions.add(
      this.monitoringService.protocolMetrics$.subscribe((metrics) => {
        this.protocolMetrics = metrics;
      })
    );

    // Horloge cadencée à 1 seconde (1000 ms) pour mettre à jour l'heure murale
    // et les chronomètres de durée de panne (ex: `00m 42s`)
    this.subscriptions.add(
      interval(1000).subscribe(() => {
        this.nowTimestampMs = Date.now();
        this.updateClockDisplay();
      })
    );
  }

  /**
   * Liste des microservices actuellement hors-ligne (`status === 'DOWN'`).
   */
  public get downServices(): MicroserviceItem[] {
    return this.microservices.filter((service) => service.status === 'DOWN');
  }

  /**
   * Liste des microservices actuellement ralentis (`status === 'DEGRADED'`).
   */
  public get degradedServices(): MicroserviceItem[] {
    return this.microservices.filter((service) => service.status === 'DEGRADED');
  }

  /**
   * Liste des microservices pleinement opérationnels (`status === 'UP'`).
   */
  public get upServices(): MicroserviceItem[] {
    return this.microservices.filter((service) => service.status === 'UP');
  }

  /**
   * Indique si au moins un microservice est en panne (`DOWN`), ce qui active
   * le bandeau rouge clignotant et le halo lumineux sur tout le cadre TV.
   */
  public get hasDownService(): boolean {
    return this.downServices.length > 0;
  }

  /**
   * Indique si le microservice critique `core` (Interface Client) est coupé (`DOWN`).
   */
  public get isCoreServiceDown(): boolean {
    return this.microservices.some(
      (service) => service.slug === 'core' && service.status === 'DOWN'
    );
  }

  /**
   * Calcule la latence moyenne des microservices en ligne (en millisecondes).
   */
  public get averageLatencyMs(): number {
    const online = this.microservices.filter((s) => s.status !== 'DOWN');
    if (online.length === 0) {
      return 0;
    }
    const sum = online.reduce((acc, item) => acc + item.latency_ms, 0);
    return Math.round(sum / online.length);
  }

  /**
   * Permet à l'opérateur (ou au jury) de basculer à chaud entre
   * `WEBSOCKET` ⚡, `SSE` 📡 et `POLLING` 🔄.
   */
  public selectTransportMode(mode: RealtimeTransportMode): void {
    this.monitoringService.switchTransportMode(mode);
  }

  /**
   * Bascule l'affichage du panneau "Mémo Examen Oral : Pourquoi WebSockets vs SSE vs Polling ?".
   */
  public toggleExamMemo(): void {
    this.isExamMemoExpanded = !this.isExamMemoExpanded;
  }

  /**
   * Déclenche une simulation d'état (`DOWN`, `DEGRADED`, `UP`) sur un microservice donné.
   */
  public simulateStatus(slug: MicroserviceSlug, status: ServiceStatus): void {
    this.monitoringService.simulateServiceStatus(slug, status);
  }

  /**
   * Rétablit immédiatement les 5 microservices à l'état `UP`.
   */
  public recoverAllImmediately(): void {
    this.monitoringService.recoverAllServices();
  }

  /**
   * Formate le chronomètre de panne en direct (`ex: 00m 42s` ou `02h 14m 09s`)
   * à partir du timestamp `down_since` d'un microservice hors-ligne.
   */
  public formatDowntimeStopwatch(downSinceIso: string | null): string {
    if (!downSinceIso) {
      return '00m 01s';
    }
    const startMs = new Date(downSinceIso).getTime();
    const elapsedSeconds = Math.max(1, Math.floor((this.nowTimestampMs - startMs) / 1000));
    return this.formatSecondsToReadableDuration(elapsedSeconds);
  }

  /**
   * Formate une durée en secondes sous la forme `00m 42s`.
   */
  public formatSecondsToReadableDuration(totalSeconds: number | null): string {
    if (totalSeconds === null || totalSeconds <= 0) {
      return '00m 01s';
    }
    const hours = Math.floor(totalSeconds / 3600);
    const minutes = Math.floor((totalSeconds % 3600) / 60);
    const seconds = totalSeconds % 60;

    const minStr = String(minutes).padStart(2, '0');
    const secStr = String(seconds).padStart(2, '0');

    if (hours > 0) {
      const hrStr = String(hours).padStart(2, '0');
      return `${hrStr}h ${minStr}m ${secStr}s`;
    }
    return `${minStr}m ${secStr}s`;
  }

  /**
   * Suivi de performance Angular `*ngFor` par `slug` de microservice.
   */
  public trackByServiceSlug(_index: number, item: MicroserviceItem): string {
    return item.slug;
  }

  /**
   * Suivi de performance Angular `*ngFor` par `id` d'incident.
   */
  public trackByIncidentId(_index: number, item: IncidentLogItem): string {
    return item.id;
  }

  private updateClockDisplay(): void {
    const now = new Date();
    this.currentWallboardClock = now.toLocaleTimeString('fr-BE', {
      hour: '2-digit',
      minute: '2-digit',
      second: '2-digit',
    });
  }

  public ngOnDestroy(): void {
    this.subscriptions.unsubscribe();
  }
}
