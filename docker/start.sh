#!/bin/sh
set -eu

maintenance_mode=$(printf '%s' "${ARMS_MAINTENANCE_MODE:-false}" | tr '[:upper:]' '[:lower:]')

case "$maintenance_mode" in
    1|true|yes|on)
        cp /etc/nginx/arms-maintenance.conf /etc/nginx/http.d/default.conf
        exec nginx -g 'daemon off;'
        ;;
    *)
        php-fpm -D
        exec nginx -g 'daemon off;'
        ;;
esac
