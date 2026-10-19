#!/bin/bash

set -e

cp /app/.env /app/.env.dev

composer install  --no-interaction

php -f .env.local.template > .env.local

frankenphp php-server --root /app/public public/index.php