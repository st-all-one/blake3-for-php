# syntax=docker/dockerfile:1
FROM php:8.4.27-fpm-trixie@sha256:af1728147713459bda545446e3fae81cea5119543c46f28b9a077c7b3ff149c2

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_HOME=/tmp/composer \
    LC_ALL=C \
    TZ=UTC

COPY --from=composer:2@sha256:b6dcd72ca98fa19d0e5b8476de179859704891fad06dd9e0b96833d4c430a5fc /usr/bin/composer /usr/local/bin/composer

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip $PHPIZE_DEPS \
    && pecl install pcov-1.0.12 \
    && docker-php-ext-enable pcov \
    && apt-get purge -y --auto-remove $PHPIZE_DEPS \
    && rm -rf /var/lib/apt/lists/* /tmp/pear

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
