# Backend image: PHP 8.5.12 CLI + Composer, served by PHP's built-in web server.
FROM composer:2 AS composer

FROM php:8.5.12-cli

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer /usr/bin/composer /usr/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1

WORKDIR /app

# Dependencies first: this layer is cached until composer.json / composer.lock change.
COPY composer.json composer.lock* ./
RUN composer install --no-interaction --prefer-dist --no-scripts --no-autoloader

COPY . .
RUN composer dump-autoload --optimize && php bin/console cache:clear

EXPOSE 8000
CMD ["php", "-S", "0.0.0.0:8000", "-t", "public"]
