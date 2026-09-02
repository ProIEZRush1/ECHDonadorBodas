#!/bin/bash
set -e

# Reuse one key across web, queue and scheduler when Coolify has not injected one.
# The shared storage volume keeps encrypted tenant credentials decryptable.
if [ -z "$APP_KEY" ]; then
    KEY_FILE="/var/www/html/storage/.app_key"
    if [ -f "$KEY_FILE" ]; then
        APP_KEY="$(sed -n '1p' "$KEY_FILE")"
        export APP_KEY
    elif [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
        APP_KEY="$(php artisan key:generate --show --no-interaction)"
        export APP_KEY
        printf '%s\n' "$APP_KEY" > "$KEY_FILE"
        chmod 600 "$KEY_FILE"
    else
        echo "APP_KEY is missing and the shared key has not been initialized" >&2
        exit 1
    fi
fi

# Cache configuration
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Only the web container owns schema migrations. Queue/scheduler wait for it.
if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    php artisan migrate --force
fi

# Fix permissions
chmod -R 775 storage bootstrap/cache

exec "$@"
