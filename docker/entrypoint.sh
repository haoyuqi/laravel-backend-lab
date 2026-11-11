#!/bin/sh
set -eu

mkdir -p /tmp/nginx

cd /var/www/html

for directory in \
    storage/app/public \
    storage/backup \
    storage/bing-wallpaper \
    storage/framework/cache \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache
do
    mkdir -p "$directory" 2>/dev/null || true

    if [ ! -w "$directory" ]; then
        echo "ERROR: $directory is not writable by uid $(id -u)." >&2
        echo "Fix the mounted volume ownership (for example: chown -R 82:82 <host-path>)." >&2
        exit 1
    fi
done

exec "$@"
