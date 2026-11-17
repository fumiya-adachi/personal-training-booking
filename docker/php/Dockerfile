FROM php:8.4-fpm-alpine AS base

ARG COMPOSER_VERSION=2.9.2
RUN apk add --no-cache git unzip curl icu-libs oniguruma libzip sqlite-libs \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS icu-dev oniguruma-dev libzip-dev sqlite-dev \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql pdo_sqlite mbstring intl zip pcntl opcache \
    && cd /tmp \
    && curl -fsSL "https://getcomposer.org/download/${COMPOSER_VERSION}/composer.phar" -o composer.phar \
    && curl -fsSL "https://getcomposer.org/download/${COMPOSER_VERSION}/composer.phar.sha256sum" -o composer.phar.sha256sum \
    && sha256sum -c composer.phar.sha256sum \
    && mv composer.phar /usr/local/bin/composer \
    && chmod +x /usr/local/bin/composer \
    && rm composer.phar.sha256sum \
    && apk del .build-deps

COPY docker/php/php.ini /usr/local/etc/php/conf.d/application.ini
WORKDIR /var/www/html
CMD ["php-fpm"]

# Opt-in local development image. The default/final stage stays Xdebug-free.
FROM base AS debug
ARG XDEBUG_VERSION=3.5.3
RUN apk add --no-cache --virtual .xdebug-build-deps $PHPIZE_DEPS linux-headers \
    && pecl install xdebug-${XDEBUG_VERSION} \
    && docker-php-ext-enable xdebug \
    && apk del .xdebug-build-deps \
    && rm -rf /tmp/pear
COPY docker/php/xdebug.ini /usr/local/etc/php/conf.d/zz-xdebug.ini

FROM base AS production
