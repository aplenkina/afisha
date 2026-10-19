#!/bin/sh
set -e

for name in APP_SECRET POSTGRES_USER POSTGRES_PASSWORD; do
    if [ ! -r "/run/secrets/$name" ]; then
        echo "entrypoint: не найден секрет /run/secrets/$name" >&2
        exit 1
    fi
done

php -f .env.template > .env.local
php -f .env.test.template > .env.test.local

exec docker-php-entrypoint "$@"
