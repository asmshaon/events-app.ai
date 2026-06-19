#!/usr/bin/env bash
# Shared entrypoint for the app/queue/scheduler containers.
#
# Only the `app` service sets INSTALL_DEPS/RUN_MIGRATIONS=true: it installs
# Composer dependencies and runs migrations once. The other containers wait for
# vendor/ to appear so they never race the install or run migrations twice.
set -e

cd /var/www/html

# Make sure an .env exists (the repo ships only .env.example).
if [ ! -f .env ]; then
    cp .env.example .env
fi

# Sync the connection settings the compose file injects as env vars into .env.
# `php artisan serve` is a long-lived process that resolves env() from the .env
# FILE, not the container's OS env — so without this the web app would fall back
# to the .env default (sqlite) while the CLI uses mysql. Keep them in lockstep.
set_env() {
    local key="$1" value="$2"
    if [ -n "$value" ]; then
        if grep -qE "^${key}=" .env; then
            # Use a non-/ delimiter so values with slashes don't break sed.
            sed -i "s|^${key}=.*|${key}=${value}|" .env
        else
            echo "${key}=${value}" >> .env
        fi
    fi
}

for var in DB_CONNECTION DB_HOST DB_PORT DB_DATABASE DB_USERNAME DB_PASSWORD MAIL_MAILER MAIL_HOST MAIL_PORT; do
    eval "set_env \"$var\" \"\${$var:-}\""
done

if [ "${INSTALL_DEPS:-false}" = "true" ]; then
    if [ ! -f vendor/autoload.php ]; then
        echo "[entrypoint] Installing Composer dependencies..."
        composer install --no-interaction --prefer-dist
    fi

    if ! grep -q '^APP_KEY=base64:' .env; then
        echo "[entrypoint] Generating application key..."
        php artisan key:generate --force
    fi
else
    echo "[entrypoint] Waiting for vendor/ (installed by the app container)..."
    until [ -f vendor/autoload.php ]; do sleep 2; done
fi

# Wait for MySQL to accept TCP connections before doing anything DB-related.
if [ -n "${DB_HOST:-}" ]; then
    echo "[entrypoint] Waiting for database at ${DB_HOST}:${DB_PORT:-3306}..."
    until php -r "exit(@fsockopen(getenv('DB_HOST'), (int)(getenv('DB_PORT') ?: 3306)) ? 0 : 1);" 2>/dev/null; do
        sleep 2
    done
fi

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    echo "[entrypoint] Running migrations..."
    php artisan migrate --force
    php artisan storage:link 2>/dev/null || true
fi

exec "$@"