<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Seeder principal de l'application Laravel pour la supervision des microservices de la Ville de Wavre.
 */
class DatabaseSeeder extends Seeder
{
    /**
     * Exécute l'ensemble des seeders du projet.
     */
    public function run(): void
    {
        $this->call([
            MicroserviceSeeder::class,
        ]);
    }
}
