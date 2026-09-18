#!/usr/bin/env bash
set -e

echo "==> Installing PHP 8.3 with MySQL/GD/Xdebug extensions"
sudo apt-get update -y
sudo apt-get install -y php8.3-cli php8.3-mysql php8.3-gd php8.3-mbstring \
    php8.3-xml php8.3-curl php8.3-zip php8.3-bcmath php8.3-intl \
    php8.3-sqlite3 php8.3-common php8.3-readline php8.3-xdebug

echo "==> Configuring Xdebug (debug mode, port 9003)"
if ! grep -q "xdebug.mode=debug" /etc/php/8.3/mods-available/xdebug.ini 2>/dev/null; then
    echo 'xdebug.mode=debug,develop
xdebug.start_with_request=yes
xdebug.client_port=9003' | sudo tee -a /etc/php/8.3/mods-available/xdebug.ini > /dev/null
fi

echo "==> Switching default 'php' to the PHP 8.3 build (the codespace's stock PHP lacks pdo_mysql/gd)"
sudo mkdir -p /opt/php-8.3-full/bin
sudo ln -sf /usr/bin/php8.3 /opt/php-8.3-full/bin/php
rm -f "$HOME/.php/current"
ln -s /opt/php-8.3-full "$HOME/.php/current"
hash -r

echo "==> Installing PHP dependencies"
composer install --no-interaction

echo "==> Preparing .env"
if [ ! -f .env ]; then
    cp .env.example .env
    sed -i \
        -e 's/^DB_CONNECTION=.*/DB_CONNECTION=mysql/' \
        -e 's/^# DB_HOST=.*/DB_HOST=127.0.0.1/' \
        -e 's/^# DB_PORT=.*/DB_PORT=3306/' \
        -e 's/^# DB_DATABASE=.*/DB_DATABASE=laravel/' \
        -e 's/^# DB_USERNAME=.*/DB_USERNAME=root/' \
        -e 's/^# DB_PASSWORD=.*/DB_PASSWORD=cmms_secret/' \
        .env
    php artisan key:generate --ansi
fi

echo "==> Installing JS dependencies"
npm install

echo "==> Starting MySQL + phpMyAdmin"
docker-compose up -d

echo "==> Waiting for MySQL to become healthy"
for i in $(seq 1 30); do
    status=$(docker inspect --format='{{.State.Health.Status}}' cmms-mysql-1 2>/dev/null || echo "")
    if [ "$status" = "healthy" ]; then
        break
    fi
    sleep 2
done

echo "==> Running migrations"
php artisan migrate --force

echo "==> Done. Press F5, or run the '🚀 Start Everything' task, to start the app."
