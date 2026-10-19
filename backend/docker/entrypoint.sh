#!/usr/bin/env bash
set -e

export PORT="${PORT:-80}"

sed "s/\${PORT}/${PORT}/g" /var/www/html/docker/default.conf > /etc/nginx/http.d/default.conf

mkdir -p /var/www/html/storage/framework/cache \
         /var/www/html/storage/framework/sessions \
         /var/www/html/storage/framework/views \
         /var/www/html/storage/logs \
         /var/www/html/bootstrap/cache
chmod -R 777 /var/www/html/storage /var/www/html/bootstrap/cache || true

if [ -f /var/www/html/composer.json ] && [ ! -f /var/www/html/vendor/autoload.php ]; then
    composer install --no-interaction --prefer-dist --optimize-autoloader
fi

if [ -f /var/www/html/vendor/autoload.php ]; then
    php artisan config:clear || true
    php artisan route:clear || true
    php artisan migrate --force || true
    php artisan db:seed --force || true
fi

exec /usr/bin/supervisord -c /etc/supervisord.conf
