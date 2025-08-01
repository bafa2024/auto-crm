#!/bin/bash

# ACRM Docker Entrypoint Script

set -e

echo "Starting ACRM Docker container..."

# Wait for MySQL to be ready
echo "Waiting for MySQL to be ready..."
while ! mysqladmin ping -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" -p"$DB_PASS" --silent; do
    sleep 1
done
echo "MySQL is ready!"

# Set proper permissions
echo "Setting file permissions..."
chown -R www-data:www-data /var/www/html
chmod -R 755 /var/www/html
chmod -R 777 /var/www/html/logs
chmod -R 777 /var/www/html/uploads
chmod -R 777 /var/www/html/temp
chmod -R 777 /var/www/html/sessions
chmod -R 777 /var/www/html/cache
chmod -R 777 /var/www/html/database

# Create SQLite database if it doesn't exist
if [ ! -f /var/www/html/database/autocrm_local.db ]; then
    echo "Creating SQLite database..."
    sqlite3 /var/www/html/database/autocrm_local.db "SELECT 1;"
    chmod 777 /var/www/html/database/autocrm_local.db
fi

# Install Composer dependencies if vendor directory doesn't exist
if [ ! -d /var/www/html/vendor ]; then
    echo "Installing Composer dependencies..."
    composer install --no-dev --optimize-autoloader --no-interaction
fi

# Start Apache
echo "Starting Apache..."
exec apache2-foreground 