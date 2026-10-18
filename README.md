# Company Device Manager

Production-oriented company device management: **Laravel 12** API + Blade admin, **Flutter Android** agent, Sanctum dual auth (admins vs devices), enrollment codes, heartbeat/status, consent-based location, remote commands, and employee-approved live sessions.

## Toolchain (verified 2026-10-15)

| Component | Version |
|-----------|---------|
| PHP | 8.2.12 |
| Composer | 2.9.2 |
| Laravel | 12.70.0 |
| Sanctum | 4.3.3 |
| Flutter / Dart | 3.44.7 / 3.12.2 |
| Java | 17 |
| MariaDB (XAMPP) | 10.4.32 |

## Structure

```
backend/          Laravel API + Blade admin
mobile/           Flutter Android app
research/         Architecture & decisions
deployment/       Nginx, systemd, EC2 scripts
docs/             ARCHITECTURE, API, SECURITY, …
scripts/          Local setup helpers
IMPLEMENTATION_STATUS.md
```

## Backend (local)

```bash
cd backend
composer install
php artisan migrate
php artisan db:seed
php artisan serve
```

- Admin UI: http://127.0.0.1:8000/admin/login  
- Default admin: `admin@example.com` / `ChangeMeNow!123` (**change immediately**)  
- API: http://127.0.0.1:8000/api/v1  
- Health: http://127.0.0.1:8000/up  
- Tests: `php artisan test`

## Flutter (Android)

```bash
cd mobile
flutter pub get
flutter run --dart-define=API_BASE_URL=http://10.0.2.2:8000
```

Physical device: use your PC LAN IP instead of `10.0.2.2`.

```bash
flutter analyze
flutter test
flutter build apk --dart-define=API_BASE_URL=https://your-api.example.com
```

**Note:** APK build requires a working Android SDK (`ANDROID_HOME`). This environment currently reports SDK missing.

## Testing EC2 deploy

Target path: `/var/www/html/testing`. See `deployment/README.md` and `docs/DEPLOYMENT.md`.  
Scripts require `CONFIRM=yes` and are non-destructive by default.

## Docs

- [IMPLEMENTATION_STATUS.md](IMPLEMENTATION_STATUS.md) — phase status & blockers  
- [docs/API.md](docs/API.md) · [docs/SECURITY.md](docs/SECURITY.md) · [docs/DATABASE.md](docs/DATABASE.md)
