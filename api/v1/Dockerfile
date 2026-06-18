FROM php:apache

RUN docker-php-ext-install mysqli

# Build context is ./api (one level above this Dockerfile).
# Copy all api/ files to /var/www/html/api/ so the URL prefix /api/ is
# preserved in the container, matching the production path-routing model.
COPY . /var/www/html/api/
