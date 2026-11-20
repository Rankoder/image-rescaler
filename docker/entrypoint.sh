#!/bin/sh
set -e

composer install --no-interaction

mkdir -p storage/input
# Orphaned partial files left if the container was killed mid-copy
find storage -maxdepth 2 -type f -name '.[0-9a-f][0-9a-f][0-9a-f][0-9a-f][0-9a-f][0-9a-f][0-9a-f][0-9a-f][0-9a-f][0-9a-f][0-9a-f][0-9a-f][0-9a-f][0-9a-f][0-9a-f][0-9a-f]-*' -delete
# The sample images; -n keeps an image the user replaced or added under the same name
cp -n resources/images/* storage/input/
chown www-data:www-data storage storage/input storage/input/*

exec docker-php-entrypoint "$@"
