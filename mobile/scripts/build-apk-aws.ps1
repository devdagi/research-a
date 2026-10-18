# Build / run CDM Android app against AWS EC2 http://54.175.235.43
$ErrorActionPreference = "Stop"
$ApiBase = if ($env:CDM_API_BASE) { $env:CDM_API_BASE } else { "http://54.175.235.43" }

Set-Location (Split-Path $PSScriptRoot -Parent)

Write-Host "API_BASE_URL=$ApiBase"

if ($args -contains "run") {
  flutter run --dart-define=API_BASE_URL=$ApiBase @($args | Where-Object { $_ -ne "run" })
} else {
  flutter build apk --release --dart-define=API_BASE_URL=$ApiBase
  Write-Host "APK: build\app\outputs\flutter-apk\app-release.apk"
}
