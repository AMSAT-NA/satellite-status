# Built and pushed to ACR by .github/workflows/cd.yml
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

COPY . /var/www/html/
