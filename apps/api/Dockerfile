# syntax=docker/dockerfile:1

ARG PHP_VERSION=8.4-cli-alpine

FROM composer:2.10.2 AS composer

FROM php:${PHP_VERSION} AS development

RUN apk add --no-cache \
        icu-libs \
        libxml2 \
        oniguruma \
        libpq \
        libpng \
        libjpeg-turbo \
        libwebp \
        sqlite-libs \
    && apk add --no-cache --virtual .build-dependencies \
        $PHPIZE_DEPS \
        icu-dev \
        libpq-dev \
        libpng-dev \
        libjpeg-turbo-dev \
        libwebp-dev \
        libxml2-dev \
        oniguruma-dev \
        sqlite-dev \
    && docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" \
        dom \
        gd \
        intl \
        mbstring \
        pcntl \
        pdo_pgsql \
        pdo_sqlite \
        xml \
        xmlwriter \
    && pecl install protobuf-5.35.1 \
    && docker-php-ext-enable protobuf \
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

CMD ["php", "-S", "0.0.0.0:8000", "-t", "public", "public/index.php"]

FROM php:8.4-apache-bookworm AS production

ARG VCS_REF="unknown"
LABEL org.opencontainers.image.revision="${VCS_REF}" \
      org.opencontainers.image.source="https://github.com/davidoliver1984/dolved"

RUN apt-get update \
    && apt-get install --yes --no-install-recommends \
        libicu-dev \
        libonig-dev \
        libpq-dev \
        libpng-dev \
        libjpeg62-turbo-dev \
        libwebp-dev \
        libxml2-dev \
        libzip-dev \
        unzip \
    && docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" \
        dom \
        gd \
        intl \
        mbstring \
        pcntl \
        pdo_pgsql \
        xml \
        xmlwriter \
        zip \
    && pecl install protobuf-5.35.1 \
    && docker-php-ext-enable protobuf \
    && a2enmod headers rewrite \
    && sed -ri -e 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!/var/www/html/public!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer /usr/bin/composer /usr/local/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --no-scripts \
    --no-autoloader

COPY --chown=www-data:www-data . .
COPY --from=contracts --chown=www-data:www-data . /var/contracts
COPY --chmod=755 docker-entrypoint.sh /usr/local/bin/docker-entrypoint

RUN composer dump-autoload --classmap-authoritative --no-dev \
    && chown -R www-data:www-data storage bootstrap/cache vendor

ENV APP_ENV=production
ENV APP_DEBUG=false
ENV LOG_CHANNEL=stderr

EXPOSE 80

ENTRYPOINT ["docker-entrypoint"]
CMD ["apache2-foreground"]
