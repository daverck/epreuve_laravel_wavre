<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Exécute la migration pour créer la table principale `microservices`.
     * Cette table stocke l'état courant et la configuration des 5 microservices
     * de la Ville de Wavre (core, mailing, carto, voirie, salle).
     */
    public function up(): void
    {
        Schema::create('microservices', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 50)->unique();
            $table->string('name', 120);
            $table->text('description');
            $table->string('container_name', 100);
            $table->string('health_url', 255);
            $table->unsignedSmallInteger('port');
            $table->enum('status', ['UP', 'DOWN', 'DEGRADED'])->default('UP');
            $table->boolean('is_simulated_down')->default(false);
            $table->boolean('is_critical')->default(false);
            $table->unsignedInteger('response_time_ms')->default(12);
            $table->decimal('uptime_percentage', 5, 2)->default(100.00);
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamp('last_status_change_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Annule la migration en supprimant la table `microservices`.
     */
    public function down(): void
    {
        Schema::dropIfExists('microservices');
    }
};
