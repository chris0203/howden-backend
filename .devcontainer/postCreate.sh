#!/bin/sh

cp .env.local .env
composer install -n --no-scripts

# Clearing cache
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

php artisan migrate --force
php artisan db:seed --force
