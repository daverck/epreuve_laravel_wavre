<?php

namespace App\Events;

use App\Models\Microservice;
use App\Models\ServiceLog;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Événement temps réel diffusé instantanément (`ShouldBroadcastNow`) dès qu'un
 * des 5 microservices de la Ville de Wavre change d'état (UP, DOWN, DEGRADED).
 * Permet de notifier l'écran mural (Wallboard Angular) via WebSocket sans délai de file d'attente.
 */
class MicroserviceStatusUpdated implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * Crée une nouvelle instance de l'événement de changement d'état.
     *
     * @param  array<string, mixed>  $dashboard
     */
    public function __construct(
        public Microservice $microservice,
        public ServiceLog $serviceLog,
        public array $dashboard = [],
    ) {
    }

    /**
     * Canal public sur lequel l'écran mural (Wallboard) écoute les alertes temps réel.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('microservices-wallboard'),
        ];
    }

    /**
     * Nom explicite de l'événement côté client WebSocket.
     */
    public function broadcastAs(): string
    {
        return 'microservice.status.updated';
    }

    /**
     * Charge utile JSON transmise aux abonnés WebSocket.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'event' => 'microservice.status.updated',
            'microservice' => $this->microservice->toArray(),
            'log' => $this->serviceLog->toArray(),
            'dashboard' => $this->dashboard,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
