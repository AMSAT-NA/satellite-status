# Built and pushed to ACR by .github/workflows/cd.yml.
# Build context is ./api/v1: everything the v1 API image needs lives in
# api/v1/, so a change elsewhere (e.g. api/v2) never rebuilds this image.
# The web code is api/v1/*.php and lib/; image-only files (the gpdata-sync
# CLI job, its crontab, the entrypoint) are in api/v1/docker/. The /api
# versions index (#38) is served by api-v2 (api/v2/src/routes/versions.ts).
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

# /api and /api/ are api-v2's (the versions index; Traefik routes them
# there). If they ever reach this container, 404 them: otherwise mod_dir
# would 301 /api to /api/, and Apache could list the /api/ directory.
#
# Also 404 the build and image-only files under /api/v1/ (Dockerfile,
# .dockerignore, docker/). The image removes them from the web root
# below; this rule covers the local dev stack too, which bind-mounts
# api/v1/ over the web root (docker-compose.yml).
RUN printf '%s\n' \
        'RedirectMatch 404 "^/api/?$"' \
        'RedirectMatch 404 "^/api/v1/(Dockerfile|\.dockerignore|docker(/.*)?)$"' \
        > /etc/apache2/conf-available/api-root.conf \
    && a2enconf api-root

# The web root gets api/v1/ minus its build and image-only files, so the
# Dockerfile, .dockerignore and docker/ (the CLI job outside the web root,
# its crontab, the entrypoint) are never publicly fetchable. CI's smoke
# test checks they answer 404.
COPY . /var/www/html/api/v1/
RUN rm -rf /var/www/html/api/v1/Dockerfile /var/www/html/api/v1/.dockerignore /var/www/html/api/v1/docker

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
COPY docker/bin/ /opt/satellite-status/bin/
COPY docker/crontab /etc/satellite-status/crontab
COPY docker/docker-entrypoint.sh /usr/local/bin/satellite-status-entrypoint
RUN chmod +x /usr/local/bin/satellite-status-entrypoint

ENTRYPOINT ["satellite-status-entrypoint"]
CMD ["apache2-foreground"]
