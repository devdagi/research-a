#!/usr/bin/env bash
# Local development setup (Linux/macOS) for Company Device Manager.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BACKEND="${ROOT}/backend"
MOBILE="${ROOT}/mobile"

need() {
  if ! command -v "$1" >/dev/null 2>&1; then
    echo "Missing required command: $1" >&2
    exit 1
  fi
}

need php
need composer

if [[ ! -d "${BACKEND}" ]]; then
  echo "Backend not found at ${BACKEND}" >&2
  exit 1
fi

cd "${BACKEND}"

if [[ ! -f .env ]]; then
  cp .env.example .env
  php artisan key:generate
fi

composer install

php artisan migrate --force

echo ""
echo "Start API (default http://127.0.0.1:8000):"
echo "  cd backend && php artisan serve"
echo ""
echo "Optional queue worker:"
echo "  cd backend && php artisan queue:work"
echo ""
if command -v flutter >/dev/null 2>&1 && [[ -d "${MOBILE}" ]]; then
  echo "Flutter (Android emulator API host):"
  echo "  cd mobile && flutter pub get"
  echo "  flutter run --dart-define=API_BASE_URL=http://10.0.2.2:8000"
else
  echo "Install Flutter SDK to run the mobile app from ${MOBILE}"
fi
