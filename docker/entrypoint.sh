#!/bin/sh
set -e

# Миграции запускает только app-контейнер (RUN_MIGRATIONS=true);
# postgres к этому моменту healthy (см. depends_on в docker-compose.yml),
# ретраи — страховка от гонки на самом старте.
if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    tries=0
    until php artisan migrate --force; do
        tries=$((tries + 1))
        if [ "$tries" -ge 30 ]; then
            echo "Database is not ready, giving up" >&2
            exit 1
        fi
        echo "Database is not ready, retrying ($tries/30)..."
        sleep 2
    done

    php artisan l5-swagger:generate
fi

exec "$@"
