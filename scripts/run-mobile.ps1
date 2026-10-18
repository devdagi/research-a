# Run Flutter against this PC's Wi-Fi LAN IP.
$ErrorActionPreference = "Stop"
$Mobile = Join-Path (Split-Path -Parent $PSScriptRoot) "mobile"
$ApiBase = "http://192.168.8.109:8000"

Push-Location $Mobile
try {
    Write-Host "API_BASE_URL=$ApiBase"
    flutter run --dart-define=API_BASE_URL=$ApiBase @args
}
finally {
    Pop-Location
}
