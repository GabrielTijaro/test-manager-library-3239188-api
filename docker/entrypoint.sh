#!/bin/sh
set -e


php artisan migrate --force

php artisan library:ensure-admin

exec php artisan serve --host=0.0.0.0 --port=8000
