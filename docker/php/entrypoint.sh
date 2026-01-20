#!/bin/sh
set -e

# Attendre que la base de données soit prête
echo "Waiting for database..."
until pg_isready -h db -p 5432 -U parry; do
  echo "Database is unavailable - sleeping"
  sleep 1
done
echo "Database is ready!"

# Lancer les migrations Doctrine (optionnel)
php bin/console doctrine:migrations:migrate --no-interaction || true

# Démarrer le serveur de développement Symfony sur le port 8000
exec php -S 0.0.0.0:8000 -t public