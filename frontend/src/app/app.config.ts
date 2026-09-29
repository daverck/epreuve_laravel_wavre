/**
 * ============================================================================
 * VILLE DE WAVRE — CONFIGURATION GLOBALE ANGULAR 18 (STANDALONE ARCHITECTURE)
 * ============================================================================
 * Fournit le client HTTP réactif (`provideHttpClient`) requis par le service
 * de supervision hybride (REST API, Server-Sent Events et WebSockets).
 */
import { ApplicationConfig, provideZoneChangeDetection } from '@angular/core';
import { provideHttpClient, withFetch } from '@angular/common/http';

export const appConfig: ApplicationConfig = {
  providers: [
    // Optimisation de la détection de changements Zone.js avec coalescence d'événements
    provideZoneChangeDetection({ eventCoalescing: true }),
    // Activation du HttpClient natif avec support de l'API Fetch moderne
    provideHttpClient(withFetch()),
  ],
};
