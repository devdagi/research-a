#!/usr/bin/env bash
# Company Device Manager — non-destructive preflight for testing deploy target.
set -euo pipefail

DEPLOY_PATH="${CDM_DEPLOY_PATH:-/var/www/html/testing}"
MIN_PHP_MAJOR=8
MIN_PHP_MINOR=2
MIN_DISK_MB=2048
MIN_MEM_MB=512

errors=0
warnings=0

log_ok()   { printf '[OK] %s\n' "$*"; }
log_warn() { printf '[WARN] %s\n' "$*"; warnings=$((warnings + 1)); }
log_err()  { printf '[ERROR] %s\n' "$*"; errors=$((errors + 1)); }

detect_os() {
  if [[ -f /etc/os-release ]]; then
    # shellcheck disable=SC1091
    . /etc/os-release
    log_ok "OS: ${PRETTY_NAME:-unknown} (${ID:-unknown})"
  elif [[ "$(uname -s)" == "Darwin" ]]; then
    log_ok "OS: macOS $(sw_vers -productVersion 2>/dev/null || echo unknown)"
  else
    log_warn "Could not detect OS from /etc/os-release"
  fi
}

check_php() {
  if ! command -v php >/dev/null 2>&1; then
    log_err "PHP not found in PATH"
    return
  fi
  local ver
  ver="$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION.".".PHP_RELEASE_VERSION;')"
  local major minor
  major="$(php -r 'echo PHP_MAJOR_VERSION;')"
  minor="$(php -r 'echo PHP_MINOR_VERSION;')"
  if (( major < MIN_PHP_MAJOR )) || { (( major == MIN_PHP_MAJOR )) && (( minor < MIN_PHP_MINOR )); }; then
    log_err "PHP ${ver} found; need >= ${MIN_PHP_MAJOR}.${MIN_PHP_MINOR}"
  else
    log_ok "PHP ${ver}"
  fi
  for ext in pdo pdo_mysql mbstring openssl tokenizer xml ctype json fileinfo bcmath; do
    if php -m 2>/dev/null | grep -qi "^${ext}$"; then
      log_ok "PHP extension: ${ext}"
    else
      log_err "Missing PHP extension: ${ext}"
    fi
  done
}

check_composer() {
  if command -v composer >/dev/null 2>&1; then
    # Avoid composer network/update prompts that hang on EC2.
    local ver
    ver="$(COMPOSER_DISABLE_NETWORK=1 timeout 10 composer -V 2>/dev/null | head -1 || true)"
    if [[ -n "${ver}" ]]; then
      log_ok "Composer: ${ver}"
    else
      log_ok "Composer present ($(command -v composer))"
    fi
  else
    log_warn "Composer not in PATH (required on deploy host or CI)"
  fi
}

check_web_server() {
  if systemctl is-active --quiet nginx 2>/dev/null; then
    log_ok "Nginx is active"
  elif systemctl is-active --quiet apache2 2>/dev/null || systemctl is-active --quiet httpd 2>/dev/null; then
    log_ok "Apache is active"
  elif command -v nginx >/dev/null 2>&1; then
    log_warn "Nginx installed but service state unknown"
  elif command -v apache2 >/dev/null 2>&1 || command -v httpd >/dev/null 2>&1; then
    log_warn "Apache installed but service state unknown"
  else
    log_warn "No nginx/apache detected (ok for CI-only checks)"
  fi

  if [[ -d /etc/nginx/sites-enabled ]]; then
    if grep -rl "root ${DEPLOY_PATH}" /etc/nginx/sites-enabled/ 2>/dev/null | grep -q .; then
      log_ok "Nginx site references ${DEPLOY_PATH}"
    else
      log_warn "No enabled Nginx vhost yet for ${DEPLOY_PATH} (install deployment/nginx/testing.conf)"
    fi
  fi
}

check_mysql() {
  if command -v mysql >/dev/null 2>&1 || command -v mariadb >/dev/null 2>&1; then
    log_ok "MySQL/MariaDB client present"
  else
    log_warn "mysql/mariadb client not found"
  fi
  if systemctl is-active --quiet mariadb 2>/dev/null || systemctl is-active --quiet mysql 2>/dev/null; then
    log_ok "MariaDB/MySQL service active"
  else
    log_warn "MariaDB/MySQL service not active (verify remote DB if applicable)"
  fi
}

check_resources() {
  local avail_kb
  avail_kb="$(df -Pk "${DEPLOY_PATH%/*}" 2>/dev/null | awk 'NR==2 {print $4}' || df -Pk / | awk 'NR==2 {print $4}')"
  if [[ -n "${avail_kb}" ]]; then
    local avail_mb=$((avail_kb / 1024))
    if (( avail_mb < MIN_DISK_MB )); then
      log_warn "Free disk ~${avail_mb}MB (recommend >= ${MIN_DISK_MB}MB)"
    else
      log_ok "Free disk ~${avail_mb}MB"
    fi
  fi
  if command -v free >/dev/null 2>&1; then
    local mem_mb
    mem_mb="$(free -m | awk '/^Mem:/ {print $7}')"
    if [[ -n "${mem_mb}" ]] && (( mem_mb < MIN_MEM_MB )); then
      log_warn "Available memory ~${mem_mb}MB (recommend >= ${MIN_MEM_MB}MB for queue + PHP-FPM)"
    else
      log_ok "Available memory ~${mem_mb:-unknown}MB"
    fi
  fi
}

inspect_deploy_path() {
  if [[ ! -e "${DEPLOY_PATH}" ]]; then
    log_ok "Deploy path ${DEPLOY_PATH} does not exist yet (first deploy)"
    return
  fi

  if [[ ! -d "${DEPLOY_PATH}" ]]; then
    log_err "${DEPLOY_PATH} exists but is not a directory"
    return
  fi

  if [[ -f "${DEPLOY_PATH}/artisan" ]]; then
    if grep -q "Company Device Manager\|Laravel" "${DEPLOY_PATH}/composer.json" 2>/dev/null; then
      log_ok "Existing Laravel app at ${DEPLOY_PATH}"
    else
      log_err "artisan present but composer.json does not look like CDM/Laravel — possible app conflict"
    fi
  elif [[ -f "${DEPLOY_PATH}/wp-config.php" ]]; then
    log_err "WordPress detected at ${DEPLOY_PATH} — unexpected conflict for CDM testing path"
  elif [[ -f "${DEPLOY_PATH}/index.html" ]] && [[ ! -f "${DEPLOY_PATH}/public/index.php" ]]; then
    log_warn "Static or unknown site at ${DEPLOY_PATH}; deploy will replace contents (backup first)"
  else
    log_warn "Directory exists without artisan — may be partial deploy or foreign content"
  fi

  if [[ -f "${DEPLOY_PATH}/.env" ]]; then
    log_ok ".env present (will be preserved by deploy script unless overridden)"
  fi
}

main() {
  echo "=== CDM preflight (${DEPLOY_PATH}) ==="
  detect_os
  check_php
  check_composer
  check_web_server
  check_mysql
  check_resources
  inspect_deploy_path

  echo "---"
  echo "Warnings: ${warnings}, Errors: ${errors}"
  if (( errors > 0 )); then
    exit 1
  fi
  exit 0
}

main "$@"
