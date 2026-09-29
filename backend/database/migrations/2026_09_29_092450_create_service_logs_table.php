<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Exécute la migration pour créer la table `service_logs` (journal d'audit 1:N).
     * Chaque changement d'état (UP, DOWN, DEGRADED) est tracé avec calcul de la durée
     * d'indisponibilité (`downtime_duration_seconds`) lors du rétablissement.
     */
    public function up(): void
    {
        Schema::create('service_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('microservice_id')
                ->constrained('microservices')
                ->cascadeOnDelete();
            $table->enum('previous_status', ['UP', 'DOWN', 'DEGRADED']);
            $table->enum('new_status', ['UP', 'DOWN', 'DEGRADED']);
            $table->enum('trigger_source', ['SIMULATION_UI', 'DOCKER_PROBE', 'SYSTEM_INIT'])
                ->default('SIMULATION_UI');
            $table->string('message', 255);
            $table->unsignedInteger('downtime_duration_seconds')->nullable();
            $table->timestamps();

            $table->index(['microservice_id', 'created_at']);
        });
    }

    /**
     * Annule la migration en supprimant la table `service_logs`.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_logs');
    }
};
