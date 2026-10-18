# Run CDM on emulator using host Ethernet IP (works when 10.0.2.2 is broken by VPN).
$ErrorActionPreference = "Stop"
$Root = Split-Path -Parent $PSScriptRoot
$Mobile = Join-Path $Root "mobile"
$SdkAdb = Join-Path $env:LOCALAPPDATA "Android\Sdk\platform-tools\adb.exe"

# Prefer stable host LAN IP serving XAMPP /cdm (update if your Wi‑Fi IP changes)
$Api = "http://192.168.8.109/cdm"

# Prefer Android SDK adb (C:\Windows\adb.exe v40 clashes with SDK v41)
$env:Path = "$(Split-Path $SdkAdb);C:\flutter\bin;$env:Path"
Get-Process adb -ErrorAction SilentlyContinue | Stop-Process -Force -ErrorAction SilentlyContinue
Start-Sleep -Seconds 1
& $SdkAdb start-server | Out-Null
& $SdkAdb wait-for-device | Out-Null

if (-not (Test-Path "C:\xampp\htdocs\cdm")) {
    cmd /c mklink /J "C:\xampp\htdocs\cdm" "$Root\backend\public" | Out-Null
}

Write-Host "API_BASE_URL=$Api"
Write-Host "Admin: http://127.0.0.1/cdm/admin/login"
Write-Host "Tip: disconnect HotspotShield/OpenVPN if enroll still times out."

Push-Location $Mobile
try {
    flutter run -d emulator-5554 --dart-define=API_BASE_URL=$Api @args
}
finally {
    Pop-Location
}
