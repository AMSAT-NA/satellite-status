#!/bin/sh
# API container entrypoint (Issue #25): starts the job scheduler in the
# background, then hands over to the official PHP image's entrypoint and
# Apache exactly as before.
#
# The scheduler runs as www-data, not root: jobs only need the database
# and outbound HTTPS. If it stops, Apache keeps serving; a missed sync shows
# up as an old last run on the admin dashboard.
#
# GPDATA_SCHEDULER=off skips it (local dev, CI smoke tests).
set -e

if [ "${GPDATA_SCHEDULER:-on}" != "off" ]; then
    setpriv --reuid=www-data --regid=www-data --init-groups \
        supercronic /etc/satellite-status/crontab &
fi

exec docker-php-entrypoint "$@"
