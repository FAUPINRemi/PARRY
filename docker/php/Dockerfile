FROM php:8.3-fpm-alpine

RUN apk add --no-cache \
    bash git unzip icu-dev libpq-dev libzip-dev oniguruma-dev postgresql-client \
    && docker-php-ext-install intl pdo pdo_pgsql opcache zip

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY docker/php/php.ini /usr/local/etc/php/php.ini

WORKDIR /var/www/symfony

COPY backend-parry-symfony/composer.* ./

RUN composer install --no-dev --no-scripts --prefer-dist --no-interaction

COPY backend-parry-symfony/ ./

COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 8000

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]