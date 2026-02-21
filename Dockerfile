FROM dunglas/frankenphp

# Install extensions
RUN install-php-extensions pcntl bcmath gd intl zip opcache pdo_mysql redis

COPY . /app
WORKDIR /app

# Run permissions and optimizations
RUN composer install --optimize-autoloader --no-dev
RUN php artisan optimize

# The actual start command
CMD php artisan storage:link --force && php artisan migrate --force && frankenphp php-server