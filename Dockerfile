# syntax=docker/dockerfile:1

# Local development image. Every PHP process of the stack (web, Horizon,
# scheduler) and Vite run from it; the project folder is bind-mounted, so
# vendor/ and node_modules/ are installed from inside the containers:
#
#   docker compose run --rm app composer install
#   docker compose run --rm vite npm ci
FROM php:8.5-cli-bookworm

RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip curl libzip-dev libicu-dev \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql bcmath intl pcntl zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && rm -rf /var/lib/apt/lists/* /tmp/pear

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/php/app.ini "$PHP_INI_DIR/conf.d/app.ini"

WORKDIR /var/www/html

EXPOSE 8000
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
