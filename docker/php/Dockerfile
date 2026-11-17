FROM php:8.4-fpm-alpine

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
