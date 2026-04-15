#!/bin/sh
set -e

echo "Vérification des dépendances Composer..."
composer install --no-interaction --prefer-dist --no-progress

echo "Attente de la base de données..."
until pg_isready -h db -p 5432 -U parry > /dev/null 2>&1; do
  echo "BDD indisponible, nouvelle tentative..."
  sleep 2 
done

echo "Base de données prête, attente supplémentaire..."
sleep 3

php bin/console doctrine:database:create --if-not-exists 2>/dev/null || true

echo "Exécution des migrations..."
php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

echo "Démarrage du serveur Symfony..."
export PHP_CLI_SERVER_WORKERS=8
exec php -S 0.0.0.0:8000 -t public