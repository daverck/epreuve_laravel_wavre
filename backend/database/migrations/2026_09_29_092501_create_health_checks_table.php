<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Exécute la migration pour créer la table `health_checks` (télémétrie HTTP 1:N).
     * Enregistre les sondes périodiques envoyées aux conteneurs Docker.
     */
    public function up(): void
    {
        Schema::create('health_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('microservice_id')
                ->constrained('microservices')
                ->cascadeOnDelete();
            $table->unsignedSmallInteger('http_status_code');
            $table->unsignedInteger('latency_ms');
            $table->boolean('is_healthy');
            $table->timestamp('checked_at');

            $table->index(['microservice_id', 'checked_at']);
        });
    }

    /**
     * Annule la migration en supprimant la table `health_checks`.
     */
    public function down(): void
    {
        Schema::dropIfExists('health_checks');
    }
};
