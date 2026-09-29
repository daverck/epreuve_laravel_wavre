<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modèle Eloquent représentant une entrée dans le journal des incidents (`service_logs`).
 * Trace chaque transition d'état (UP, DOWN, DEGRADED) ainsi que la durée d'indisponibilité.
 */
class ServiceLog extends Model
{
    /**
     * Attributs assignables en masse.
     *
     * @var list<string>
     */
    protected $fillable = [
        'microservice_id',
        'previous_status',
        'new_status',
        'trigger_source',
        'message',
        'downtime_duration_seconds',
    ];

    /**
     * Attributs calculés exposés dans la réponse JSON conformément au contrat OpenAPI 3.0.
     *
     * @var list<string>
     */
    protected $appends = [
        'microservice_slug',
        'microservice_name',
    ];

    /**
     * Relations cachées pour éviter la redondance dans le JSON sérialisé.
     *
     * @var list<string>
     */
    protected $hidden = [
        'microservice',
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
            'downtime_duration_seconds' => 'integer',
        ];
    }

    /**
     * Relation N:1 vers le microservice concerné par cet événement.
     *
     * @return BelongsTo<Microservice, $this>
     */
    public function microservice(): BelongsTo
    {
        return $this->belongsTo(Microservice::class, 'microservice_id');
    }

    /**
     * Accesseur retournant le slug du microservice associé (ex: `voirie`).
     */
    public function getMicroserviceSlugAttribute(): ?string
    {
        return $this->microservice?->slug;
    }

    /**
     * Accesseur retournant le libellé complet du microservice associé.
     */
    public function getMicroserviceNameAttribute(): ?string
    {
        return $this->microservice?->name;
    }
}
