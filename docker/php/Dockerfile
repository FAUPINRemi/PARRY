FROM php:8.3-fpm-alpine

# Installation des dépendances système et extensions PHP
RUN apk add --no-cache \
    bash git unzip icu-dev libpq-dev libzip-dev oniguruma-dev postgresql-client \
    && docker-php-ext-install intl pdo pdo_pgsql opcache zip

# Installation de Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/php/php.ini /usr/local/etc/php/php.ini

WORKDIR /var/www/symfony

# Installation des dépendances Composer
COPY backend-parry-symfony/composer.* ./
ENV COMPOSER_ALLOW_SUPERUSER=1
RUN composer update --no-scripts
RUN composer install --no-scripts

# Copie du code de l'application
COPY backend-parry-symfony/ ./
RUN composer run-script post-install-cmd || true

# Entrypoint
COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 8000
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]