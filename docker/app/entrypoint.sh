#!/bin/sh
# App container entrypoint.
# Rebuilds Laravel's caches from the CURRENT runtime environment each time the
# container starts, so a configuration change (e.g. APP_DEBUG) takes effect
# after the container is recreated (Lab 9: config refresh). Migrations are NOT
# run automatically - they follow the approved migration procedure (Lab 8).
set -e

if [ "${1:-}" = "php-fpm" ]; then
    if [ -z "${APP_KEY:-}" ]; then
        echo "APP_KEY is not set in the runtime environment (.env.container / .env.staging)." >&2
        exit 1
    fi

    if [ "${APP_CONFIG_CACHE:-true}" = "true" ]; then
        php artisan config:cache --no-ansi
        php artisan route:cache --no-ansi
        php artisan view:cache --no-ansi
    else
        php artisan optimize:clear --no-ansi
    fi
fi

exec "$@"
