#!/usr/bin/env bash
# Backup CDM testing instance: .env, storage/, and MariaDB dump.
set -euo pipefail

DEPLOY_PATH="${CDM_DEPLOY_PATH:-/var/www/html/testing}"
BACKUP_ROOT="${CDM_BACKUP_ROOT:-/var/backups/cdm-testing}"
TIMESTAMP="$(date +%Y%m%d-%H%M%S)"
BACKUP_DIR="${BACKUP_ROOT}/${TIMESTAMP}"
ARCHIVE="${BACKUP_ROOT}/cdm-testing-${TIMESTAMP}.tar.gz"

mkdir -p "${BACKUP_DIR}"

if [[ ! -f "${DEPLOY_PATH}/.env" ]]; then
  echo "ERROR: ${DEPLOY_PATH}/.env not found; aborting backup." >&2
  exit 1
fi

cp -a "${DEPLOY_PATH}/.env" "${BACKUP_DIR}/.env"

if [[ -d "${DEPLOY_PATH}/storage" ]]; then
  cp -a "${DEPLOY_PATH}/storage" "${BACKUP_DIR}/storage"
else
  echo "WARN: storage/ missing at ${DEPLOY_PATH}" >&2
fi

# Load DB credentials from .env (no secrets printed)
set -a
# shellcheck disable=SC1091
source /dev/stdin <<EOF
$(grep -E '^(DB_CONNECTION|DB_HOST|DB_PORT|DB_DATABASE|DB_USERNAME|DB_PASSWORD)=' "${DEPLOY_PATH}/.env" | sed 's/\r$//')
EOF
set +a

if [[ "${DB_CONNECTION:-mysql}" != "sqlite" ]] && [[ -n "${DB_DATABASE:-}" ]]; then
  dump_file="${BACKUP_DIR}/database.sql"
  mysqldump \
    --host="${DB_HOST:-127.0.0.1}" \
    --port="${DB_PORT:-3306}" \
    --user="${DB_USERNAME:-root}" \
    ${DB_PASSWORD:+-p"${DB_PASSWORD}"} \
    --single-transaction \
    --routines \
    --triggers \
    "${DB_DATABASE}" > "${dump_file}"
  chmod 600 "${dump_file}"
  echo "Database dump: ${dump_file}"
else
  echo "Skipping mysqldump (sqlite or DB_DATABASE unset)"
fi

tar -czf "${ARCHIVE}" -C "${BACKUP_ROOT}" "${TIMESTAMP}"
chmod 600 "${ARCHIVE}"
echo "Backup archive: ${ARCHIVE}"

# Keep manifest for rollback.sh
echo "${ARCHIVE}" > "${BACKUP_ROOT}/LATEST"
