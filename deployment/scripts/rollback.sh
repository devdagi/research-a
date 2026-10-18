#!/usr/bin/env bash
# Restore CDM testing from backup tarball created by backup.sh
set -euo pipefail

if [[ "${CONFIRM:-}" != "yes" ]]; then
  echo "Refusing to rollback: set CONFIRM=yes to proceed." >&2
  exit 1
fi

DEPLOY_PATH="${CDM_DEPLOY_PATH:-/var/www/html/testing}"
BACKUP_ROOT="${CDM_BACKUP_ROOT:-/var/backups/cdm-testing}"
ARCHIVE="${1:-}"

if [[ -z "${ARCHIVE}" ]] && [[ -f "${BACKUP_ROOT}/LATEST" ]]; then
  ARCHIVE="$(cat "${BACKUP_ROOT}/LATEST")"
fi

if [[ ! -f "${ARCHIVE}" ]]; then
  echo "ERROR: Backup archive not found: ${ARCHIVE:-<unset>}" >&2
  echo "Usage: CONFIRM=yes $0 [/path/to/cdm-testing-YYYYMMDD-HHMMSS.tar.gz]" >&2
  exit 1
fi

STAGING="$(mktemp -d)"
trap 'rm -rf "${STAGING}"' EXIT

tar -xzf "${ARCHIVE}" -C "${STAGING}"
RESTORE_DIR="$(find "${STAGING}" -mindepth 1 -maxdepth 1 -type d | head -1)"
if [[ -z "${RESTORE_DIR}" ]]; then
  echo "ERROR: invalid backup archive layout" >&2
  exit 1
fi

mkdir -p "${DEPLOY_PATH}"

if [[ -f "${RESTORE_DIR}/.env" ]]; then
  cp -a "${RESTORE_DIR}/.env" "${DEPLOY_PATH}/.env"
  chmod 600 "${DEPLOY_PATH}/.env"
fi

if [[ -d "${RESTORE_DIR}/storage" ]]; then
  rm -rf "${DEPLOY_PATH}/storage"
  cp -a "${RESTORE_DIR}/storage" "${DEPLOY_PATH}/storage"
fi

if [[ -f "${RESTORE_DIR}/database.sql" ]]; then
  set -a
  # shellcheck disable=SC1091
  source /dev/stdin <<EOF
$(grep -E '^(DB_CONNECTION|DB_HOST|DB_PORT|DB_DATABASE|DB_USERNAME|DB_PASSWORD)=' "${DEPLOY_PATH}/.env" | sed 's/\r$//')
EOF
  set +a
  if [[ "${DB_CONNECTION:-mysql}" != "sqlite" ]] && [[ -n "${DB_DATABASE:-}" ]]; then
    mysql \
      --host="${DB_HOST:-127.0.0.1}" \
      --port="${DB_PORT:-3306}" \
      --user="${DB_USERNAME:-root}" \
      ${DB_PASSWORD:+-p"${DB_PASSWORD}"} \
      "${DB_DATABASE}" < "${RESTORE_DIR}/database.sql"
  fi
fi

WEB_USER="${CDM_WEB_USER:-www-data}"
WEB_GROUP="${CDM_WEB_GROUP:-www-data}"
chown -R "${WEB_USER}:${WEB_GROUP}" "${DEPLOY_PATH}/storage" 2>/dev/null || true

cd "${DEPLOY_PATH}"
php artisan config:clear
php artisan cache:clear

echo "Rollback restored from ${ARCHIVE}"
echo "Restart services: sudo systemctl restart php8.2-fpm cdm-queue.service"
