# Built and pushed to ACR by .github/workflows/cd.yml.
# Build context is ./api (not ./api/v1) so the /api front door
# (api/index.php) ships in the same image as v1 -- see issue #38.
FROM php:8.5-apache

RUN docker-php-ext-install mysqli \
    && apt-get update \
    && apt-get install -y --no-install-recommends curl \
    && rm -rf /var/lib/apt/lists/*

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
