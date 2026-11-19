#!/usr/bin/env bash
# Starts MySQL natively when docker/docker-compose isn't available on this
# machine (this devcontainer's base image ships without them even though
# devcontainer.json requests the docker-in-docker feature).
set -e

if pgrep -x mysqld > /dev/null; then
    echo "==> MySQL already running"
    exit 0
fi

if ! command -v mysqld > /dev/null; then
    echo "==> Installing mysql-server"
    sudo apt-get update -y
    sudo apt-get install -y mysql-server
fi

if ! sudo test -d /var/lib/mysql/mysql; then
    echo "==> Initializing MySQL data directory"
    sudo mkdir -p /run/mysqld /var/lib/mysql
    sudo chown -R mysql:mysql /run/mysqld /var/lib/mysql
    sudo mysqld --initialize-insecure --user=mysql --datadir=/var/lib/mysql
fi

echo "==> Starting MySQL"
sudo mkdir -p /run/mysqld
sudo chown -R mysql:mysql /run/mysqld
sudo sh -c "nohup mysqld --user=mysql --datadir=/var/lib/mysql --socket=/run/mysqld/mysqld.sock > /tmp/mysql.log 2>&1 &"

ready=
for i in $(seq 1 30); do
    if mysqladmin ping -h127.0.0.1 -P3306 --silent 2>/dev/null; then
        echo "==> MySQL is ready"
        ready=1
        break
    fi
    sleep 1
done

if [ -z "$ready" ]; then
    echo "==> MySQL did not become ready in time" >&2
    exit 1
fi

if [ -f .env ]; then
    db_name=$(grep -m1 '^DB_DATABASE=' .env | cut -d= -f2-)
    db_pass=$(grep -m1 '^DB_PASSWORD=' .env | cut -d= -f2-)
    db_name=${db_name:-laravel}

    echo "==> Ensuring database '$db_name' and root password match .env"
    sudo mysql <<SQL
CREATE DATABASE IF NOT EXISTS \`${db_name}\`;
ALTER USER 'root'@'localhost' IDENTIFIED WITH mysql_native_password BY '${db_pass}';
CREATE USER IF NOT EXISTS 'root'@'127.0.0.1' IDENTIFIED WITH mysql_native_password BY '${db_pass}';
ALTER USER 'root'@'127.0.0.1' IDENTIFIED WITH mysql_native_password BY '${db_pass}';
GRANT ALL PRIVILEGES ON *.* TO 'root'@'127.0.0.1' WITH GRANT OPTION;
FLUSH PRIVILEGES;
SQL
fi

exit 0
