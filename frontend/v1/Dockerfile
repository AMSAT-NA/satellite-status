# Built and pushed to ACR by .github/workflows/cd.yml
FROM php:8.6-rc-apache

# mbstring: Parsedown (banner rendering) calls mb_strlen. The official
# PHP 8.6 images no longer ship mbstring (8.5's did), so install it when
# it's missing. --disable-mbregex skips Oniguruma, whose mb_ereg_*
# functions are deprecated in 8.6 and unused here (Issue #37).
RUN docker-php-ext-install mysqli \
    && (php -m | grep -qx mbstring || { docker-php-ext-configure mbstring --disable-mbregex && docker-php-ext-install mbstring; }) \
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
