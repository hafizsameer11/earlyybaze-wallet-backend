# Stage 1: Build dependencies
FROM composer:latest AS build
WORKDIR /app
COPY . /app

# We use --no-scripts to prevent Laravel from trying to boot 
# during the build process when environment variables aren't present.
RUN composer install \
    --ignore-platform-reqs \
    --no-interaction \
    --no-scripts \
    --optimize-autoloader \
    --no-dev

# Stage 2: Production image
FROM dunglas/frankenphp:latest

# Install PHP extensions required for Laravel
RUN install-php-extensions pcntl bcmath gd intl zip opcache pdo_mysql redis

# Copy the app from the build stage
COPY --from=build /app /app
WORKDIR /app

# Set correct permissions for the web server (www-data)
RUN chown -R www-data:www-data /app/storage /app/bootstrap/cache

# Expose the port FrankenPHP listens on
EXPOSE 80

# THE RUNTIME SEQUENCE
# These commands run only when Dokploy starts the container, 
# ensuring they have access to the variables in the "Environment" tab.
CMD php artisan storage:link --force && \
    php artisan optimize && \
    php artisan migrate --force && \
    frankenphp php-server