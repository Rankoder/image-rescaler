#!/bin/sh
set -e

composer install --no-interaction

mkdir -p storage/input
chown www-data:www-data storage storage/input

exec docker-php-entrypoint "$@"
