# syntax=docker/dockerfile:1.7

FROM composer:2 AS composer

FROM dunglas/frankenphp:1.12.6-php8.4-alpine

RUN install-php-extensions \
    intl \
    mbstring \
    opcache \
    pdo_pgsql

COPY --from=composer /usr/bin/composer /usr/bin/composer

WORKDIR /app

ENV COMPOSER_ALLOW_SUPERUSER=1
ENV SERVER_ROOT=/app/public

RUN cp "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"

COPY composer.json composer.lock symfony.lock ./
RUN composer install --prefer-dist --no-interaction --no-progress --no-scripts
