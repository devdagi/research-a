# Local development setup (Windows / XAMPP) for Company Device Manager.
$ErrorActionPreference = "Stop"

$Root = Split-Path -Parent $PSScriptRoot
$Backend = Join-Path $Root "backend"
$Mobile = Join-Path $Root "mobile"

function Require-Command($Name) {
    if (-not (Get-Command $Name -ErrorAction SilentlyContinue)) {
        throw "Missing required command: $Name"
    }
}

Require-Command php
Require-Command composer

if (-not (Test-Path $Backend)) {
    throw "Backend not found at $Backend"
}

Push-Location $Backend
try {
    if (-not (Test-Path ".env")) {
        Copy-Item ".env.example" ".env"
        php artisan key:generate
    }

    composer install
    php artisan migrate --force
}
finally {
    Pop-Location
}

Write-Host ""
Write-Host "XAMPP: ensure MariaDB is running; set DB_* in backend\.env (mysql, not sqlite for parity with EC2)."
Write-Host ""
Write-Host "Start API:"
Write-Host "  cd backend; php artisan serve"
Write-Host ""
Write-Host "Optional queue worker:"
Write-Host "  cd backend; php artisan queue:work"
Write-Host ""

if ((Get-Command flutter -ErrorAction SilentlyContinue) -and (Test-Path $Mobile)) {
    Write-Host "Flutter (physical device — use your LAN IP):"
    Write-Host "  cd mobile; flutter pub get"
    Write-Host "  flutter run --dart-define=API_BASE_URL=http://192.168.x.x:8000"
} else {
    Write-Host "Install Flutter to run the app from $Mobile"
}
