#!/usr/bin/env bash
set -e

export PORT="${PORT:-80}"

sed "s/\${PORT}/${PORT}/g" /var/www/html/docker/default.conf > /etc/nginx/http.d/default.conf

if [ -f /var/www/html/vendor/autoload.php ]; then
    php artisan config:clear || true
    php artisan route:clear || true
    php artisan migrate --force || true
    php artisan db:seed --force || true
fi

exec /usr/bin/supervisord -c /etc/supervisord.conf

#!/usr/bin/env bash
set -e

export PORT="${PORT:-80}"

sed "s/\${PORT}/${PORT}/g" /var/www/html/docker/default.conf > /etc/nginx/http.d/default.conf

exec /usr/bin/supervisord -c /etc/supervisord.conf

