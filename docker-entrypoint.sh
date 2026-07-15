#!/bin/sh

set -e

echo "Fixing Laravel permissions..."

mkdir -p \
    storage/framework/cache \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

chown -R www-data:www-data storage bootstrap/cache

chmod -R 775 storage bootstrap/cache

exec "$@"