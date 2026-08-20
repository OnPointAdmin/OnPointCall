#!/bin/sh
set -e
cd /var/www/html

mkdir -p \
  storage/framework/cache/data \
  storage/framework/sessions \
  storage/framework/views \
  storage/framework/testing \
  storage/logs \
  bootstrap/cache \
  vendor

# app + queue share the vendor volume; only one composer install at a time
flock vendor/.composer.lock.flock sh -c '
  if [ ! -f vendor/autoload.php ]; then
    composer install --no-interaction --prefer-dist --no-progress --no-scripts
  fi
'

# php-fpm workers run as www-data; artisan exec often runs as root
chown -R www-data:www-data storage bootstrap/cache
chmod -R ug+rwX storage bootstrap/cache

exec "$@"
