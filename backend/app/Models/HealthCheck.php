<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modèle Eloquent représentant une sonde HTTP (`health_checks`) exécutée
 * sur le point de terminaison `/health` d'un conteneur Docker.
 */
class HealthCheck extends Model
{
    /**
     * La table `health_checks` utilise uniquement la colonne `checked_at`.
     */
    public $timestamps = false;

    /**
     * Attributs assignables en masse.
     *
     * @var list<string>
     */
    protected $fillable = [
        'microservice_id',
        'http_status_code',
        'latency_ms',
        'is_healthy',
        'checked_at',
    ];

    /**
     * Conversion automatique des types SQL vers les types natifs PHP.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'microservice_id' => 'integer',
            'http_status_code' => 'integer',
            'latency_ms' => 'integer',
            'is_healthy' => 'boolean',
            'checked_at' => 'datetime',
        ];
    }

    /**
     * Relation N:1 vers le microservice sondé.
     *
     * @return BelongsTo<Microservice, $this>
     */
    public function microservice(): BelongsTo
    {
        return $this->belongsTo(Microservice::class, 'microservice_id');
    }
}
