#!/bin/sh
set -eu

# Keys are generated once and shared between FPM and the queue worker.
if [ ! -s runtime/app.env ]; then
    cp .env.example runtime/app.env
fi
if ! grep -q '^APP_KEY=base64:.' runtime/app.env; then
    php artisan key:generate --force --no-interaction
fi
if ! grep -q '^JWT_SECRET=.' runtime/app.env; then
    php artisan jwt:secret --force --no-interaction
fi

php artisan migrate --force --no-interaction
php artisan db:seed --force --no-interaction
chown -R www-data:www-data storage bootstrap/cache runtime
echo "Onfly: banco preparado e contas de demonstração disponíveis."
