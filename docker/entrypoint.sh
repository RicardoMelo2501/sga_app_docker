#!/bin/bash
set -e

host="${DATABASE_HOST:-db}"
port="${DATABASE_PORT:-5432}"

echo "Waiting for database at ${host}:${port}..."
until php -r "exit(@fsockopen('${host}', ${port}) ? 0 : 1);"; do
    sleep 2
done
echo "Database is up."

cd /var/www/html

if [ ! -f var/.installed ]; then
    echo "Running first-time setup..."
    php bin/console novosga:install --no-interaction
    touch var/.installed
else
    echo "Running database migrations..."
    php bin/console doctrine:migrations:migrate --no-interaction || true
fi

php bin/console cache:clear --env="${APP_ENV:-prod}"
php bin/console assets:install public --env="${APP_ENV:-prod}"

chown -R www-data:www-data var

exec "$@"
