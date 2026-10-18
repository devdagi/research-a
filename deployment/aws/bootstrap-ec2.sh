#!/usr/bin/env bash
# One-time EC2 bootstrap for CDM at http://54.175.235.43
# Run as root or with sudo on Ubuntu 22.04+.
set -euo pipefail

export DEBIAN_FRONTEND=noninteractive

echo "==> Installing packages"
apt-get update -y
apt-get install -y nginx mariadb-server composer unzip git curl \
  php8.2-fpm php8.2-cli php8.2-mysql php8.2-mbstring php8.2-xml \
  php8.2-curl php8.2-zip php8.2-bcmath php8.2-sqlite3

echo "==> MariaDB database"
DB_PASS="${CDM_DB_PASSWORD:-$(openssl rand -base64 18)}"
mysql -e "CREATE DATABASE IF NOT EXISTS cdm CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -e "CREATE USER IF NOT EXISTS 'cdm'@'127.0.0.1' IDENTIFIED BY '${DB_PASS}';"
mysql -e "GRANT ALL PRIVILEGES ON cdm.* TO 'cdm'@'127.0.0.1'; FLUSH PRIVILEGES;"
echo "DB password (save this): ${DB_PASS}"

REPO_ROOT="${CDM_REPO:-/opt/cdm-src}"
if [[ ! -f "${REPO_ROOT}/backend/artisan" ]]; then
  echo "ERROR: Clone the repo to ${REPO_ROOT} first (needs backend/artisan)." >&2
  echo "  git clone <your-repo-url> ${REPO_ROOT}" >&2
  exit 1
fi

echo "==> Nginx site"
cp "${REPO_ROOT}/deployment/nginx/aws-54.conf" /etc/nginx/sites-available/cdm.conf
ln -sf /etc/nginx/sites-available/cdm.conf /etc/nginx/sites-enabled/cdm.conf
rm -f /etc/nginx/sites-enabled/default
nginx -t
systemctl reload nginx

echo "==> Systemd queue + scheduler"
cp "${REPO_ROOT}/deployment/systemd/"* /etc/systemd/system/
systemctl daemon-reload
systemctl enable --now cdm-queue.service cdm-scheduler.timer

echo "==> First .env"
mkdir -p /var/www/html/testing
if [[ ! -f /var/www/html/testing/.env ]]; then
  cp "${REPO_ROOT}/deployment/aws/env.aws.example" /var/www/html/testing/.env
  sed -i "s/CHANGE_ME_STRONG_PASSWORD/${DB_PASS}/" /var/www/html/testing/.env
  # Generate APP_KEY after composer install in deploy
fi

echo "==> Deploy app"
cd "${REPO_ROOT}"
chmod +x deployment/scripts/*.sh
CONFIRM=yes ./deployment/scripts/deploy-testing.sh

cd /var/www/html/testing
if ! grep -q '^APP_KEY=base64:' .env; then
  php artisan key:generate --force
fi
php artisan migrate --force
php artisan db:seed --force
php artisan config:cache

chown -R www-data:www-data storage bootstrap/cache

echo ""
echo "Done."
echo "  Admin:  http://54.175.235.43/admin/login"
echo "  Health: http://54.175.235.43/up"
echo "  Default admin: admin@example.com / ChangeMeNow!123"
echo "  Open SG inbound TCP 80 from phones / your IP."
