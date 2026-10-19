FROM dunglas/frankenphp

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip \
    && rm -rf /var/lib/apt/lists/* \
    && install-php-extensions @composer pdo_pgsql

WORKDIR /var/www/html/app

COPY docker/frankenphp/Caddyfile /etc/caddy/Caddyfile

CMD ["frankenphp", "run", "-c", "/etc/caddy/Caddyfile"]
