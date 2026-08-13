#!/bin/sh
set -e

PORT="${PORT:-8000}"
sed -i "s/Listen 80/Listen $PORT/" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:80>/<VirtualHost *:$PORT>/" /etc/apache2/sites-available/000-default.conf

php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
php artisan storage:link --force || true
php artisan migrate --force --no-interaction

exec apache2-foreground
