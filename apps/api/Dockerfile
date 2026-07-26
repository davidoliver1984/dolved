# syntax=docker/dockerfile:1

ARG PHP_VERSION=8.4-cli-alpine

FROM composer:2.10.2 AS composer

FROM php:${PHP_VERSION} AS development

RUN apk add --no-cache \
        icu-libs \
        libxml2 \
        oniguruma \
        libpq \
        sqlite-libs \
    && apk add --no-cache --virtual .build-dependencies \
        icu-dev \
        libpq-dev \
        libxml2-dev \
        oniguruma-dev \
        sqlite-dev \
    && docker-php-ext-install -j"$(nproc)" \
        dom \
        intl \
        mbstring \
        pcntl \
        pdo_pgsql \
        pdo_sqlite \
        xml \
        xmlwriter \
    && apk del .build-dependencies

COPY --from=composer /usr/bin/composer /usr/local/bin/composer

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install \
    --no-interaction \
    --prefer-dist \
    --no-scripts \
    --no-autoloader

COPY --chown=www-data:www-data . .
COPY --chmod=755 docker-entrypoint.sh /usr/local/bin/docker-entrypoint

RUN composer dump-autoload --optimize \
    && touch .env \
    && chown -R www-data:www-data \
        .env \
        storage \
        bootstrap/cache \
        vendor

ENV APP_ENV=local
ENV APP_DEBUG=true
ENV LOG_CHANNEL=stderr
ENV DB_CONNECTION=sqlite
ENV DB_DATABASE=:memory:
ENV SESSION_DRIVER=array
ENV CACHE_STORE=array
ENV QUEUE_CONNECTION=sync

USER www-data

EXPOSE 8000

ENTRYPOINT ["docker-entrypoint"]

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
