#!/bin/bash
set -e

echo "================================================================"
echo "  Démarrage du Backend Laravel - Monitoring Ville de Wavre"
echo "================================================================"

if [ ! -d "vendor" ]; then
    echo "-> Installation des dépendances Composer..."
    composer install --no-interaction --prefer-dist
fi

if [ ! -f ".env" ]; then
    echo "-> Création du fichier .env depuis .env.example..."
    cp .env.example .env
    php artisan key:generate --force
fi

if [ "$DB_CONNECTION" = "mysql" ]; then
    echo "-> Attente de la base de données MySQL ($DB_HOST:$DB_PORT)..."
    for i in $(seq 1 30); do
        if php -r "try { new PDO('mysql:host=' . getenv('DB_HOST') . ';port=' . getenv('DB_PORT'), getenv('DB_USERNAME'), getenv('DB_PASSWORD')); exit(0); } catch (Exception \$e) { exit(1); }"; then
            echo "-> Base de données MySQL connectée !"
            break
        fi
        sleep 2
    done
fi

echo "-> Exécution des migrations et du seeder (php artisan migrate --seed)..."
php artisan migrate --seed --force

echo "-> Serveur Laravel actif sur http://0.0.0.0:8000"
exec php artisan serve --host=0.0.0.0 --port=8000