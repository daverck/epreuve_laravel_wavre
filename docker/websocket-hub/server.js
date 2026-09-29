/**
 * VILLE DE WAVRE — Serveur WebSocket Temps Réel (RFC 6455 Natif - Port 6001)
 */
const http = require('http');
const crypto = require('crypto');

const PORT = parseInt(process.env.PORT || '6001', 10);
const clients = new Set();

function encodeWebSocketFrame(payloadString) {
    const payload = Buffer.from(payloadString, 'utf8');
    const length = payload.length;
    let header;

    if (length < 126) {
        header = Buffer.alloc(2);
        header[0] = 0x81;
        header[1] = length;
    } else if (length < 65536) {
        header = Buffer.alloc(4);
        header[0] = 0x81;
        header[1] = 126;
        header.writeUInt16BE(length, 2);
    } else {
        header = Buffer.alloc(10);
        header[0] = 0x81;
        header[1] = 127;
        header.writeBigUInt64BE(BigInt(length), 2);
    }

    return Buffer.concat([header, payload]);
}

function broadcastToAll(eventObject) {
    const message = JSON.stringify({
        ...eventObject,
        ws_timestamp: new Date().toISOString(),
        connected_screens: clients.size,
    });
    const frame = encodeWebSocketFrame(message);

    for (const socket of clients) {
        try {
            socket.write(frame);
        } catch (err) {
            clients.delete(socket);
        }
    }
}

const server = http.createServer((req, res) => {
    res.setHeader('Content-Type', 'application/json; charset=utf-8');
    res.setHeader('Access-Control-Allow-Origin', '*');
    res.setHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
    res.setHeader('Access-Control-Allow-Headers', 'Content-Type');

    if (req.method === 'OPTIONS') {
        res.writeHead(204);
        res.end();
        return;
    }

    if (req.method === 'POST' && req.url === '/broadcast') {
        let body = '';
        req.on('data', (chunk) => {
            body += chunk.toString();
        });
        req.on('end', () => {
            try {
                const payload = JSON.parse(body || '{}');
                broadcastToAll({
                    event: payload.event || 'MicroserviceStatusUpdated',
                    channel: payload.channel || 'microservices-wallboard',
                    data: payload.data || {},
                });
                res.writeHead(200);
                res.end(JSON.stringify({
                    broadcasted: true,
                    clients_notified: clients.size,
                }));
            } catch (e) {
                res.writeHead(400);
                res.end(JSON.stringify({ error: 'Invalid JSON payload' }));
            }
        });
        return;
    }

    if (req.method === 'GET' && req.url === '/health') {
        res.writeHead(200);
        res.end(JSON.stringify({
            service: 'wavre-websocket-hub',
            status: 'UP',
            connected_clients: clients.size,
        }));
        return;
    }

    res.writeHead(404);
    res.end(JSON.stringify({ error: 'Not Found' }));
});

server.on('upgrade', (req, socket) => {
    const key = req.headers['sec-websocket-key'];
    if (!key) {
        socket.destroy();
        return;
    }

    const acceptKey = crypto
        .createHash('sha1')
        .update(key + '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', 'binary')
        .digest('base64');

    const headers = [
        'HTTP/1.1 101 Switching Protocols',
        'Upgrade: websocket',
        'Connection: Upgrade',
        `Sec-WebSocket-Accept: ${acceptKey}`,
        '\r\n',
    ];

    socket.write(headers.join('\r\n'));
    clients.add(socket);

    socket.write(encodeWebSocketFrame(JSON.stringify({
        event: 'ConnectionEstablished',
        channel: 'microservices-wallboard',
        message: 'Canal WebSocket temps réel connecté au Wallboard Ville de Wavre',
        connected_screens: clients.size,
        ws_timestamp: new Date().toISOString(),
    })));

    socket.on('close', () => clients.delete(socket));
    socket.on('error', () => clients.delete(socket));
});

server.listen(PORT, '0.0.0.0', () => {
    console.log(`[Ville de Wavre] Serveur WebSocket (RFC 6455) actif sur ws://0.0.0.0:${PORT}`);
});