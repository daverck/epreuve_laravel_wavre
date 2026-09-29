/**
 * ============================================================================
 * VILLE DE WAVRE - Agent de Microservice Conteneurisé (Simulateur Réel Docker)
 * ============================================================================
 * Ce script léger (zéro dépendance externe, Node.js natif) s'exécute dans chacun
 * des 5 conteneurs Docker :
 *   - ms-core    (Port 8081) : Sert l'interface client
 *   - ms-mailing (Port 8082) : Envoie les mails pour les différents processus
 *   - ms-carto   (Port 8083) : Gère le CRUD des données cartographiques
 *   - ms-voirie  (Port 8084) : Gère les arrêtés de police et autorisations de stationnement
 *   - ms-salle   (Port 8085) : Gère les locations de salles
 *
 * Il expose :
 *   1. GET  /health   -> Sonde de santé interrogée par le backend Laravel
 *   2. POST /simulate -> Permet à Laravel de simuler une panne (DOWN -> HTTP 503),
 *                        une dégradation (DEGRADED -> latence élevée) ou une
 *                        reconnexion (UP -> HTTP 200) directement dans le conteneur.
 */

const http = require('http');

const SERVICE_SLUG = process.env.SERVICE_SLUG || 'unknown';
const SERVICE_NAME = process.env.SERVICE_NAME || 'Microservice Wavre';
const SERVICE_ROLE = process.env.SERVICE_ROLE || 'Service interne';
const PORT = parseInt(process.env.PORT || '8080', 10);

// État interne temps réel du conteneur Docker
let currentState = {
    status: 'UP', // 'UP' | 'DOWN' | 'DEGRADED'
    artificialLatencyMs: 0,
    lastChangedAt: new Date().toISOString(),
    startedAt: Date.now(),
};

const server = http.createServer((req, res) => {
    // En-têtes CORS et JSON standards
    res.setHeader('Content-Type', 'application/json; charset=utf-8');
    res.setHeader('Access-Control-Allow-Origin', '*');

    // 1. Endpoint de vérification de santé (Healthcheck appelé par Laravel)
    if (req.method === 'GET' && (req.url === '/health' || req.url === '/')) {
        // Calcul d'une latence réaliste (entre 8ms et 35ms en mode UP, + latence simulée si DEGRADED)
        const baseJitterMs = Math.floor(Math.random() * 20) + 8;
        const totalDelayMs = currentState.status === 'DEGRADED'
            ? currentState.artificialLatencyMs + baseJitterMs
            : baseJitterMs;

        setTimeout(() => {
            const isHealthy = currentState.status !== 'DOWN';
            const httpCode = isHealthy ? 200 : 503;

            res.writeHead(httpCode);
            res.end(JSON.stringify({
                service: SERVICE_SLUG,
                name: SERVICE_NAME,
                role: SERVICE_ROLE,
                status: currentState.status,
                healthy: isHealthy,
                latency_ms: currentState.status === 'DOWN' ? 0 : totalDelayMs,
                uptime_seconds: Math.floor((Date.now() - currentState.startedAt) / 1000),
                last_changed_at: currentState.lastChangedAt,
                timestamp: new Date().toISOString(),
            }));
        }, currentState.status === 'DOWN' ? 5 : Math.min(totalDelayMs, 1500));
        return;
    }

    // 2. Endpoint permettant à Laravel de piloter la panne/reconnexion du conteneur
    if (req.method === 'POST' && req.url === '/simulate') {
        let body = '';
        req.on('data', (chunk) => {
            body += chunk.toString();
        });
        req.on('end', () => {
            try {
                const payload = body ? JSON.parse(body) : {};
                const requestedStatus = (payload.status || 'UP').toUpperCase();

                if (['UP', 'DOWN', 'DEGRADED'].includes(requestedStatus)) {
                    currentState.status = requestedStatus;
                    currentState.artificialLatencyMs = requestedStatus === 'DEGRADED'
                        ? (payload.latency_ms || 680)
                        : 0;
                    currentState.lastChangedAt = new Date().toISOString();
                }

                res.writeHead(200);
                res.end(JSON.stringify({
                    message: `État du conteneur [${SERVICE_SLUG}] basculé vers ${currentState.status}`,
                    state: currentState,
                }));
            } catch (error) {
                res.writeHead(400);
                res.end(JSON.stringify({ error: 'JSON invalide' }));
            }
        });
        return;
    }

    res.writeHead(404);
    res.end(JSON.stringify({ error: 'Route non trouvée' }));
});

server.listen(PORT, '0.0.0.0', () => {
    console.log(`[Ville de Wavre] Microservice "${SERVICE_SLUG}" (${SERVICE_NAME}) démarré sur le port ${PORT}`);
});

process.on('SIGTERM', () => process.exit(0));
process.on('SIGINT', () => process.exit(0));
