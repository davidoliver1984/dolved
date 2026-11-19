#!/bin/sh
set -eu

if [ -z "${APP_KEY:-}" ]; then
    if [ "${APP_ENV:-local}" = "production" ]; then
        echo "APP_KEY is required in production." >&2
        exit 1
    fi
    APP_KEY="base64:$(php -r 'echo base64_encode(random_bytes(32));')"
    export APP_KEY
fi

exec "$@"
