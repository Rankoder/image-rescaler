#!/bin/sh
set -e

composer install --no-interaction

mkdir -p storage
chown www-data:www-data storage

exec docker-php-entrypoint "$@"
