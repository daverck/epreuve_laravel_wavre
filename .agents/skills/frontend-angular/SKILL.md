---
name: frontend-angular
description: >-
  Spécialiste Frontend Angular / TypeScript / CSS pour l'écran mural (Wallboard) de monitoring des microservices de la Ville de Wavre.
  Gère l'interface de supervision par Short Polling HTTP REST (2s), les alertes visuelles critiques
  et les boutons de simulation de déconnexion/reconnexion.
---

# Agent Frontend Angular — Directives Techniques

## Rôle & Responsabilités
- Développer et maintenir le répertoire `frontend/` (Angular Standalone Components + Signals + `HttpClient` + CSS moderne).
- Concevoir un **Wallboard (Écran NOC / TV de supervision)** haute visibilité avec :
  1. **Rafraîchissement par Short Polling HTTP REST (toutes les 2s)** sur `GET /api/microservices?probe=true`.
  2. **Alerte visuelle immédiate et immanquable** (bandeau rouge clignotant + halo pulsé CSS + compteur de durée de panne) dès qu'un microservice passe à `DOWN`.
  3. **Boutons de simulation sur chaque carte microservice (`core`, `mailing`, `carto`, `voirie`, `salle`)** permettant de simuler la déconnexion (`DOWN`), le ralentissement (`DEGRADED`) et la reconnexion (`UP`).
- Commenter le code TypeScript et CSS en **français** pour l'examen oral et valider avec `ESLint`.
