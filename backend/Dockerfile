FROM php:8.3-fpm-alpine

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN apk add --no-cache \
    nginx \
    supervisor \
    bash \
    curl \
    git \
    unzip \
    libpq-dev \
    oniguruma-dev \
    && docker-php-ext-install pdo pdo_pgsql pgsql mbstring

WORKDIR /var/www/html

COPY . /var/www/html/
COPY docker/php.ini /usr/local/etc/php/conf.d/custom.ini
COPY docker/supervisord.conf /etc/supervisord.conf
COPY docker/entrypoint.sh /entrypoint.sh

RUN mkdir -p /run/nginx storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chmod -R 777 storage bootstrap/cache \
    && chmod +x /entrypoint.sh \
    && if [ -f composer.json ]; then composer install --no-interaction --prefer-dist --optimize-autoloader; fi

EXPOSE 80

ENTRYPOINT ["/entrypoint.sh"]
