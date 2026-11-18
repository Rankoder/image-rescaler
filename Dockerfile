# Shared by every target: PHP with GD, Apache serving public/
FROM php:8.5-apache AS base

# Install dependencies
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libwebp-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install gd \
    && docker-php-ext-install fileinfo

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Serve only public/, so .env, config and sources are not reachable over HTTP
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Port 8080, so Apache can start without root; no server version in headers and error pages
RUN sed -ri -e 's!^Listen 80$!Listen 8080!' /etc/apache2/ports.conf \
    && sed -ri -e 's!<VirtualHost \*:80>!<VirtualHost *:8080>!' /etc/apache2/sites-available/000-default.conf \
    && sed -ri -e 's!^ServerTokens .*!ServerTokens Prod!' -e 's!^ServerSignature .*!ServerSignature Off!' /etc/apache2/conf-available/security.conf

COPY docker/php.ini "$PHP_INI_DIR/conf.d/app.ini"

WORKDIR /var/www/html/

EXPOSE 8080


# Development: Composer, Xdebug and dev dependencies; compose bind-mounts the project dir over the copy below
FROM base AS dev

RUN apt-get update && apt-get install -y \
    git \
    unzip

RUN pecl install xdebug \
    && docker-php-ext-enable xdebug
COPY docker/xdebug.ini "$PHP_INI_DIR/conf.d/xdebug.ini"

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# The project dir is bind-mounted in dev with a different owner; let git (used by Composer) trust it
RUN git config --global --add safe.directory /var/www/html

# Dependencies first, so this layer is cached until composer.json or composer.lock changes
COPY composer.json composer.lock ./
RUN composer install --no-interaction --no-scripts --no-autoloader

COPY . /var/www/html/
RUN composer dump-autoload --optimize

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

ENTRYPOINT ["entrypoint.sh"]
CMD ["apache2-foreground"]


# Build stage for prod: production dependencies and an optimized autoloader, nothing from here ships but vendor/
FROM base AS vendor

RUN apt-get update && apt-get install -y \
    unzip

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-scripts --no-autoloader

COPY src ./src
RUN composer dump-autoload --no-dev --optimize


# Production: application code and production dependencies only, running as www-data
FROM base AS prod

COPY --from=vendor /var/www/html/vendor ./vendor
COPY config ./config
COPY public ./public
COPY src ./src
COPY routes.php ./

RUN install -d -o www-data -g www-data storage

USER www-data

HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
    CMD curl --fail --silent --output /dev/null http://localhost:8080/health || exit 1
