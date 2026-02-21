# Stage 1: Build dependencies
FROM composer:latest as build
COPY . /app
RUN composer install --ignore-platform-reqs --no-interaction --plugins-config-args="allow-plugins.kylekatarnls/update-helper=false" --optimize-autoloader --no-dev

# Stage 2: Production image
FROM dunglas/frankenphp

# Install system dependencies and PHP extensions
RUN install-php-extensions pcntl bcmath gd intl zip opcache pdo_mysql redis

# Copy the app from the build stage
COPY --from=build /app /app
WORKDIR /app

# Set permissions for Laravel
RUN chown -R www-data:www-data /app/storage /app/bootstrap/cache

# Production optimizations
RUN php artisan optimize

# The final "Start" command
CMD php artisan storage:link --force && php artisan migrate --force && frankenphp php-server