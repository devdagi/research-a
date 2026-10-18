#!/usr/bin/env bash
# One-time EC2 bootstrap for CDM at http://54.175.235.43
# Supports Ubuntu 22.04 (PHP 8.2) and 24.04 Noble (PHP 8.3).
# Run as root or with sudo.
set -euo pipefail

export DEBIAN_FRONTEND=noninteractive

# Prefer distro default PHP: 8.3 on Noble, 8.2 on Jammy.
PHP_VER="${CDM_PHP_VERSION:-}"
if [[ -z "${PHP_VER}" ]]; then
  if apt-cache show php8.3-fpm >/dev/null 2>&1; then
    PHP_VER=8.3
  elif apt-cache show php8.2-fpm >/dev/null 2>&1; then
    PHP_VER=8.2
  else
    echo "ERROR: Neither php8.3-fpm nor php8.2-fpm is available." >&2
    exit 1
  fi
fi
echo "==> Using PHP ${PHP_VER}"

echo "==> Installing packages"
apt-get update -y
apt-get install -y nginx mariadb-server unzip git curl \
  "php${PHP_VER}-fpm" "php${PHP_VER}-cli" "php${PHP_VER}-mysql" \
  "php${PHP_VER}-mbstring" "php${PHP_VER}-xml" "php${PHP_VER}-curl" \
  "php${PHP_VER}-zip" "php${PHP_VER}-bcmath" "php${PHP_VER}-sqlite3"

# Composer (official installer if apt package missing / too old)
if ! command -v composer >/dev/null 2>&1; then
  echo "==> Installing Composer"
  curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
fi

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
FPM_SOCK="/run/php/php${PHP_VER}-fpm.sock"
cp "${REPO_ROOT}/deployment/nginx/aws-54.conf" /etc/nginx/sites-available/cdm.conf
sed -i "s|unix:/run/php/php[0-9.]\\+-fpm.sock|unix:${FPM_SOCK}|g" /etc/nginx/sites-available/cdm.conf
ln -sf /etc/nginx/sites-available/cdm.conf /etc/nginx/sites-enabled/cdm.conf
rm -f /etc/nginx/sites-enabled/default
nginx -t
systemctl enable --now "php${PHP_VER}-fpm"
systemctl reload nginx

echo "==> Systemd queue + scheduler"
cp "${REPO_ROOT}/deployment/systemd/"* /etc/systemd/system/
# Point ExecStart at the installed php binary
sed -i "s|^ExecStart=/usr/bin/php |ExecStart=/usr/bin/php${PHP_VER} |" /etc/systemd/system/cdm-queue.service || true
if [[ -f /etc/systemd/system/cdm-scheduler.service ]]; then
  sed -i "s|^ExecStart=/usr/bin/php |ExecStart=/usr/bin/php${PHP_VER} |" /etc/systemd/system/cdm-scheduler.service || true
fi
systemctl daemon-reload
systemctl enable --now cdm-queue.service cdm-scheduler.timer

echo "==> First .env"
mkdir -p /var/www/html/testing
if [[ ! -f /var/www/html/testing/.env ]]; then
  cp "${REPO_ROOT}/deployment/aws/env.aws.example" /var/www/html/testing/.env
fi
# Always inject DB password without sed (base64 passwords break s///).
php -r '
  $path = "/var/www/html/testing/.env";
  $env = file_get_contents($path);
  if ($env === false) { fwrite(STDERR, "Cannot read .env\n"); exit(1); }
  $pass = $argv[1];
  $env = str_replace("CHANGE_ME_STRONG_PASSWORD", $pass, $env);
  // Also update existing DB_PASSWORD= line if placeholder already gone.
  $env = preg_replace("/^DB_PASSWORD=.*/m", "DB_PASSWORD=" . $pass, $env, 1);
  file_put_contents($path, $env);
' "${DB_PASS}"

echo "==> Deploy app"
cd "${REPO_ROOT}"
chmod +x deployment/scripts/*.sh
CONFIRM=yes bash deployment/scripts/deploy-testing.sh

cd /var/www/html/testing
if ! grep -q '^APP_KEY=base64:' .env 2>/dev/null; then
  php artisan key:generate --force
fi
php artisan migrate --force
php artisan db:seed --force
php artisan config:cache

chown -R www-data:www-data storage bootstrap/cache
systemctl restart "php${PHP_VER}-fpm" cdm-queue.service

echo ""
echo "Done."
echo "  Admin:  http://54.175.235.43/admin/login"
echo "  Health: http://54.175.235.43/up"
echo "  Default admin: admin@example.com / ChangeMeNow!123"
echo "  PHP: ${PHP_VER}  FPM: ${FPM_SOCK}"
echo "  Open SG inbound TCP 80 from phones / your IP."
