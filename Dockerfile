# Stage 1: Build dependencies
FROM composer:latest AS build
WORKDIR /app
COPY . /app

# Disable interactive plugins and install
RUN composer config allow-plugins true --no-interaction \
    && composer install --ignore-platform-reqs --no-interaction --optimize-autoloader --no-dev

# Stage 2: Production image
FROM dunglas/frankenphp

# Install PHP extensions required for Laravel
RUN install-php-extensions pcntl bcmath gd intl zip opcache pdo_mysql redis

# Copy the app from the build stage
COPY --from=build /app /app
WORKDIR /app

# Set correct permissions for Laravel
RUN chown -R www-data:www-data /app/storage /app/bootstrap/cache

# Production optimizations
# Note: This requires your .env variables to be set in Dokploy
RUN php artisan optimize

# Final start command
# This links storage, runs migrations, and starts the server
CMD php artisan storage:link --force && php artisan migrate --force && frankenphp php-server