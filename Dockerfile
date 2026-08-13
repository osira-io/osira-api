# syntax=docker/dockerfile:1.7

FROM composer:2 AS composer

FROM dunglas/frankenphp:1.12.6-php8.4-alpine AS base

RUN install-php-extensions \
    mbstring \
    opcache \
    pdo_pgsql

COPY --from=composer /usr/bin/composer /usr/bin/composer

WORKDIR /app

ENV COMPOSER_ALLOW_SUPERUSER=1
ENV SERVER_ROOT=/app/public

FROM base AS development

RUN cp "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"

COPY composer.json composer.lock symfony.lock ./
RUN composer install --prefer-dist --no-interaction --no-progress --no-scripts

FROM base AS production

RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY composer.json composer.lock symfony.lock ./
RUN composer install \
    --prefer-dist \
    --no-dev \
    --no-interaction \
    --no-progress \
    --no-scripts \
    --optimize-autoloader \
    --classmap-authoritative

COPY . ./

RUN composer dump-autoload \
    --no-dev \
    --no-interaction \
    --no-scripts \
    --optimize \
    --classmap-authoritative \
    && mkdir -p var/cache var/log \
    && chmod -R 0775 var

ENV APP_ENV=prod
ENV APP_DEBUG=0
