#!/bin/sh
set -e

if [ ! -r /run/secrets/APP_SECRET ]; then
    echo "entrypoint: не найден секрет /run/secrets/APP_SECRET" >&2
    exit 1
fi

php -f .env.template > .env.local

exec docker-php-entrypoint "$@"
