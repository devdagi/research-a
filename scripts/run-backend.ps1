# Start Laravel API reachable from phone / LAN (binds 0.0.0.0).
$ErrorActionPreference = "Stop"
$Backend = Join-Path (Split-Path -Parent $PSScriptRoot) "backend"
$LanIp = "192.168.8.109"

Push-Location $Backend
try {
    Write-Host "Serving on http://127.0.0.1:8000 and http://${LanIp}:8000"
    php artisan serve --host=0.0.0.0 --port=8000
}
finally {
    Pop-Location
}
