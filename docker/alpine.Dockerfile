# syntax=docker/dockerfile:1
FROM php:8.4.27-fpm-alpine3.24@sha256:93aa3f61216b73cc6f878235c9772849449f4efc18dfe3d6678eed645076c00d

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_HOME=/tmp/composer \
    LC_ALL=C \
    TZ=UTC

COPY --from=composer:2@sha256:b6dcd72ca98fa19d0e5b8476de179859704891fad06dd9e0b96833d4c430a5fc /usr/bin/composer /usr/local/bin/composer

RUN apk add --no-cache git unzip autoconf g++ gcc make pkgconf re2c linux-headers \
    && pecl install pcov-1.0.12 \
    && docker-php-ext-enable pcov \
    && apk del autoconf g++ gcc make pkgconf re2c linux-headers \
    && rm -rf /tmp/pear

RUN { \
        echo 'date.timezone=UTC'; \
        echo 'memory_limit=512M'; \
        echo 'error_reporting=-1'; \
        echo 'display_errors=1'; \
        echo 'zend.assertions=1'; \
        echo 'pcov.enabled=1'; \
        echo 'pcov.directory=/app/src'; \
    } > /usr/local/etc/php/conf.d/zz-blake3-dev.ini

WORKDIR /app
