#!/bin/sh
set -e

# Installer/mettre à jour les dépendances automatiquement
echo "Vérification des dépendances Composer..."
composer update --no-interaction

# Attendre que la base de données soit prête
echo "Chargement bdd"
until pg_isready -h db -p 5432 -U parry; do
  echo "BDD indisponible"
  sleep 1 
done
echo "Base de donnée ok "

# Lancer les migrations Doctrine
php bin/console doctrine:migrations:migrate --no-interaction || true

# Démarrer le serveur de développement Symfony sur le port 8000
exec php -S 0.0.0.0:8000 -t public