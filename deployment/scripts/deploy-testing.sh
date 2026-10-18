#!/usr/bin/env bash
# Deploy CDM Laravel backend to /var/www/html/testing (non-destructive to other vhosts).
set -euo pipefail

if [[ "${CONFIRM:-}" != "yes" ]]; then
  echo "Refusing to deploy: set CONFIRM=yes to proceed." >&2
  exit 1
fi

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"
SOURCE="${CDM_SOURCE:-${REPO_ROOT}/backend}"
DEPLOY_PATH="${CDM_DEPLOY_PATH:-/var/www/html/testing}"
WEB_USER="${CDM_WEB_USER:-www-data}"
WEB_GROUP="${CDM_WEB_GROUP:-www-data}"
BACKUP_ROOT="${CDM_BACKUP_ROOT:-/var/backups/cdm-testing}"

if [[ ! -f "${SOURCE}/artisan" ]]; then
  echo "ERROR: Laravel app not found at ${SOURCE}" >&2
  exit 1
fi

if [[ -f "${DEPLOY_PATH}/artisan" ]] && [[ -f "${DEPLOY_PATH}/composer.json" ]]; then
  if ! grep -q '"laravel/framework"' "${DEPLOY_PATH}/composer.json" 2>/dev/null; then
    echo "ERROR: ${DEPLOY_PATH} contains a non-Laravel app; aborting." >&2
    exit 1
  fi
fi

if [[ "${SKIP_PREFLIGHT:-}" == "yes" ]]; then
  echo "Skipping preflight (SKIP_PREFLIGHT=yes)"
else
  echo "Running preflight..."
  bash "${SCRIPT_DIR}/preflight-check.sh"
fi

if [[ -d "${DEPLOY_PATH}" ]] && [[ -f "${DEPLOY_PATH}/.env" ]]; then
  echo "Creating pre-deploy backup..."
  CDM_DEPLOY_PATH="${DEPLOY_PATH}" CDM_BACKUP_ROOT="${BACKUP_ROOT}" \
    bash "${SCRIPT_DIR}/backup.sh" || echo "WARN: backup skipped"
fi

mkdir -p "${DEPLOY_PATH}"

RSYNC_EXCLUDES=(
  --exclude '.env'
  --exclude 'storage/logs/*'
  --exclude 'storage/framework/cache/data/*'
  --exclude 'storage/framework/sessions/*'
  --exclude 'storage/framework/views/*'
  --exclude 'node_modules/'
  --exclude '.git/'
  --exclude 'vendor/'
)

if ! command -v rsync >/dev/null 2>&1; then
  apt-get install -y rsync >/dev/null 2>&1 || true
fi

if command -v rsync >/dev/null 2>&1; then
  rsync -a --delete "${RSYNC_EXCLUDES[@]}" "${SOURCE}/" "${DEPLOY_PATH}/"
else
  echo "WARN: rsync not found; using cp -a (no --delete)" >&2
  cp -a "${SOURCE}/." "${DEPLOY_PATH}/"
fi

if [[ ! -f "${DEPLOY_PATH}/.env" ]]; then
  if [[ -f "${SOURCE}/.env.example" ]]; then
    cp "${SOURCE}/.env.example" "${DEPLOY_PATH}/.env"
    echo "Created ${DEPLOY_PATH}/.env from .env.example — edit before going live."
  else
    echo "ERROR: No .env at deploy path; create one manually." >&2
    exit 1
  fi
fi

cd "${DEPLOY_PATH}"

if ! command -v composer >/dev/null 2>&1; then
  echo "ERROR: composer required on target host" >&2
  exit 1
fi

composer install --no-dev --optimize-autoloader --no-interaction

php artisan migrate --force --no-interaction

php artisan config:cache
php artisan route:cache
php artisan view:cache

mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache
chown -R "${WEB_USER}:${WEB_GROUP}" storage bootstrap/cache
chmod -R ug+rwx storage bootstrap/cache

echo "Deploy complete: ${DEPLOY_PATH}"
echo "Enable Nginx site: deployment/nginx/testing.conf (does not replace default site)."
echo "Restart queue: sudo systemctl restart cdm-queue.service"
