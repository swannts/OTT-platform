#!/bin/bash
set -e

# Ensure .env exists
if [ ! -f ".env" ] && [ -f ".env.example" ]; then
    echo "Creating .env from .env.example..."
    cp .env.example .env
fi

# Ensure storage and bootstrap directories exist with write permissions
mkdir -p storage/framework/cache/data \
         storage/framework/sessions \
         storage/framework/views \
         storage/logs \
         bootstrap/cache \
         storage/app/public

chmod -R 777 storage bootstrap/cache 2>/dev/null || true

# Check if vendor directory is present
if [ ! -f "vendor/autoload.php" ]; then
    echo "Installing composer dependencies..."
    composer install --no-interaction --prefer-dist --optimize-autoloader
fi

# Create symlink for public storage if missing
if [ ! -L "public/storage" ]; then
    php artisan storage:link 2>/dev/null || true
fi

# In production mode, clear stale caches and optimize routes and views
if [ "$APP_ENV" = "production" ]; then
    echo "Optimizing configuration and views for production..."
    php artisan config:clear 2>/dev/null || true
    php artisan view:cache 2>/dev/null || true
fi

echo "Starting Swann Laravel Backend..."
exec "$@"
