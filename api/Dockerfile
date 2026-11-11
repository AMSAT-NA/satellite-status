# Built and pushed to ACR by .github/workflows/cd.yml.
# Build context is ./api (not ./api/v1) so the /api front door
# (api/index.php) ships in the same image as v1 -- see issue #38.
FROM php:8.5-apache

RUN docker-php-ext-install mysqli \
    && apt-get update \
    && apt-get install -y --no-install-recommends curl \
    && rm -rf /var/lib/apt/lists/*

# Use PHP's production settings. The official image ships no php.ini, so
# PHP otherwise runs on built-in defaults: display_errors on (warnings and
# deprecations printed to visitors) and no output buffering (setcookie()
# after the page starts printing fails -- broke the callsign cookie in
# frontend submit.php). Errors still go to the container logs.
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# Serve bare /api directly instead of letting mod_dir 301 it to /api/.
# Behind Traefik, Apache only ever sees plain http, so that redirect
# would be built as http://... and cost a second hop through Traefik's
# https redirect.
RUN printf '%s\n' 'AliasMatch "^/api$" "/var/www/html/api/index.php"' \
        > /etc/apache2/conf-available/api-root.conf \
    && a2enconf api-root

# Explicit COPYs rather than `COPY .`: only the served tree lands in the
# web root, so build files (this Dockerfile, .dockerignore) are never
# publicly fetchable.
COPY index.php /var/www/html/api/index.php
COPY v1/ /var/www/html/api/v1/

# ── Scheduler for the daily gpdata-sync (Issue #25) ─────────────────────
# supercronic: a cron built for containers. It runs as a child of this
# container's entrypoint alongside Apache, passes the container's env
# (DB credentials, GPDATA_FEED_URL) through to jobs, and logs every run
# to stdout, so results show up in `docker logs`. Pinned by version and
# checksum (SHA-1 is what upstream publishes); bump via a deliberate PR.
ENV SUPERCRONIC_URL=https://github.com/aptible/supercronic/releases/download/v0.2.44/supercronic-linux-amd64 \
    SUPERCRONIC_SHA1SUM=6eb0a8e1e6673675dc67668c1a9b6409f79c37bc \
    SUPERCRONIC=supercronic-linux-amd64
RUN curl -fsSLO "$SUPERCRONIC_URL" \
    && echo "${SUPERCRONIC_SHA1SUM}  ${SUPERCRONIC}" | sha1sum -c - \
    && chmod +x "$SUPERCRONIC" \
    && mv "$SUPERCRONIC" "/usr/local/bin/${SUPERCRONIC}" \
    && ln -s "/usr/local/bin/${SUPERCRONIC}" /usr/local/bin/supercronic

# Command-line jobs live OUTSIDE the web root, so they can't be requested
# over HTTP.
COPY bin/ /opt/satellite-status/bin/
COPY crontab /etc/satellite-status/crontab
COPY docker-entrypoint.sh /usr/local/bin/satellite-status-entrypoint
RUN chmod +x /usr/local/bin/satellite-status-entrypoint

ENTRYPOINT ["satellite-status-entrypoint"]
CMD ["apache2-foreground"]
