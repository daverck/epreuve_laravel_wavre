<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modèle Eloquent représentant l'un des 5 microservices supervisés de la Ville de Wavre :
 * - `core`    : Sert l'interface client (Critique)
 * - `mailing` : Envoie les mails pour les différents processus
 * - `carto`   : Gère le CRUD des données cartographiques
 * - `voirie`  : Gère les arrêtés de police et les autorisations de stationnement
 * - `salle`   : Gère les locations de salles communales
 */
class Microservice extends Model
{
    /**
     * Attributs assignables en masse.
     *
     * @var list<string>
     */
    protected $fillable = [
        'slug',
        'name',
        'description',
        'container_name',
        'health_url',
        'port',
        'status',
        'is_simulated_down',
        'is_critical',
        'response_time_ms',
        'uptime_percentage',
        'last_checked_at',
        'last_status_change_at',
    ];

    /**
     * Attributs calculés ajoutés automatiquement lors de la sérialisation JSON pour le Wallboard Angular.
     *
     * @var list<string>
     */
    protected $appends = [
        'downtime_seconds',
    ];

    /**
     * Conversion automatique des types de colonnes SQL vers les types natifs PHP/JSON.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'port' => 'integer',
            'is_simulated_down' => 'boolean',
            'is_critical' => 'boolean',
            'response_time_ms' => 'integer',
            'uptime_percentage' => 'float',
            'last_checked_at' => 'datetime',
            'last_status_change_at' => 'datetime',
        ];
    }

    /**
     * Utilise la colonne `slug` (core, mailing, carto, voirie, salle) comme clé de routage par défaut.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * Relation 1:N vers le journal des incidents et changements d'état (`service_logs`).
     *
     * @return HasMany<ServiceLog, $this>
     */
    public function logs(): HasMany
    {
        return $this->hasMany(ServiceLog::class, 'microservice_id')
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    /**
     * Relation 1:N vers l'historique des sondes HTTP Docker (`health_checks`).
     *
     * @return HasMany<HealthCheck, $this>
     */
    public function healthChecks(): HasMany
    {
        return $this->hasMany(HealthCheck::class, 'microservice_id')
            ->orderByDesc('checked_at')
            ->orderByDesc('id');
    }

    /**
     * Accesseur `downtime_seconds` :
     * Calcule dynamiquement le nombre de secondes écoulées depuis `last_status_change_at`
     * lorsque le microservice est hors-ligne (`status === 'DOWN'`). Retourne `0` sinon.
     */
    public function getDowntimeSecondsAttribute(): int
    {
        if ($this->status !== 'DOWN' || $this->last_status_change_at === null) {
            return 0;
        }

        return max(0, (int) $this->last_status_change_at->diffInSeconds(now()));
    }
}
