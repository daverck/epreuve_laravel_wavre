/**
 * ============================================================================
 * VILLE DE WAVRE — POINT D'ENTRÉE DU WALLBOARD DE SUPERVISION (MAIN.TS)
 * ============================================================================
 * Démarre l'application Angular 18 en mode Standalone Component (sans NgModule)
 * conformément aux bonnes pratiques modernes d'architecture Frontend.
 */
import { bootstrapApplication } from '@angular/platform-browser';
import { appConfig } from './app/app.config';
import { AppComponent } from './app/app.component';

bootstrapApplication(AppComponent, appConfig).catch((err: unknown) => {
  console.error('[Wavre Wallboard] Erreur critique au démarrage Angular :', err);
});
