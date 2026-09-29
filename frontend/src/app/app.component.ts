/**
 * ============================================================================
 * VILLE DE WAVRE — COMPOSANT PRINCIPAL DU WALLBOARD NOC (APP.COMPONENT.TS)
 * ============================================================================
 * Composant Standalone Angular 18 pilotant l'écran mural (TV Wallboard) de
 * supervision des 5 microservices Docker (`core`, `mailing`, `carto`, `voirie`, `salle`)
 * via une architecture 100% WebSocket Temps Réel (`ws://localhost:6001`) et Signals.
 *
 * Fonctionnalités clés :
 *  1. Indicateur d'état WebSocket Temps Réel (`ws://localhost:6001` — Connecté / Latence < 5 ms).
 *  2. Bandeau d'alerte critique rouge clignotant (@keyframes alertPulse) + Halo TV
 *     + Chronomètre de panne en direct à la seconde près (`00m 42s`).
 *  3. Mention spéciale si le microservice `core` (Interface Client) tombe à `DOWN`.
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
  ServiceStatus,
  WebSocketConnectionState,
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

  /** Horloge murale NOC temps réel (mise à jour chaque seconde) */
  public currentWallboardClock = '';

  /** Timestamp courant (ms) rafraîchi chaque seconde pour les chronomètres de panne */
  public nowTimestampMs: number = Date.now();

  private clockSubscription: Subscription | null = null;

  public ngOnInit(): void {
    this.updateClockDisplay();

    // Horloge cadencée à 1 seconde (1000 ms) uniquement pour l'heure murale
    // et le calcul visuel des chronomètres de durée de panne (ex: `00m 42s`)
    this.clockSubscription = interval(1000).subscribe(() => {
      this.nowTimestampMs = Date.now();
      this.updateClockDisplay();
    });
  }

  /**
   * Accès réactif aux 5 microservices depuis le Signal `servicesSignal`.
   */
  public get microservices(): MicroserviceItem[] {
    return this.monitoringService.servicesSignal();
  }

  /**
   * Accès réactif au journal des incidents depuis le Signal `recentIncidentsSignal`.
   */
  public get recentIncidents(): IncidentLogItem[] {
    return this.monitoringService.recentIncidentsSignal();
  }

  /**
   * Accès réactif à l'état du canal WebSocket (`ws://localhost:6001`) depuis le Signal `wsStateSignal`.
   */
  public get wsState(): WebSocketConnectionState {
    return this.monitoringService.wsStateSignal();
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
    const elapsedSeconds = Math.max(
      1,
      Math.floor((this.nowTimestampMs - startMs) / 1000)
    );
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
    if (this.clockSubscription) {
      this.clockSubscription.unsubscribe();
      this.clockSubscription = null;
    }
  }
}
