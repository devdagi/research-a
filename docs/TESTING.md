# Testing

How to verify CDM locally and on the EC2 testing instance without destructive database commands.

## Backend (Laravel)

### Unit / feature tests

```bash
cd backend
composer install
cp .env.example .env   # if needed
php artisan key:generate
php artisan test
```

Composer script: `composer test` (clears config cache, runs PHPUnit).

### Manual API smoke (after routes exist)

```bash
php artisan serve
curl -sS http://127.0.0.1:8000/up
```

Use `php artisan route:list` to confirm `/api/v1/device/*` registration.

### Queue

```bash
php artisan queue:work --once
```

Ensure `jobs` table exists (`migrate`).

## Mobile (Flutter)

From `mobile/` (does not modify app code in this doc):

```bash
flutter pub get
dart analyze
flutter test
```

Run against local API (Android emulator):

```bash
flutter run --dart-define=API_BASE_URL=http://10.0.2.2:8000
```

Physical device: use LAN IP of dev machine, ensure firewall allows port 8000.

Optional TURN (live session NAT testing):

```bash
flutter run \
  --dart-define=API_BASE_URL=https://testing.example.com \
  --dart-define=TURN_URL=turn:turn.example.com:3478 \
  --dart-define=TURN_USERNAME=user \
  --dart-define=TURN_CREDENTIAL=secret
```

## EC2 testing checklist

1. `./deployment/scripts/preflight-check.sh` — exit 0
2. `CONFIRM=yes ./deployment/scripts/deploy-testing.sh`
3. `curl -sS -o /dev/null -w "%{http_code}\n" http://127.0.0.1/up` → `200`
4. `sudo systemctl is-active cdm-queue.service`
5. `sudo systemctl list-timers | grep cdm-scheduler`
6. Enroll test device against testing URL (HTTPS recommended)
7. Heartbeat → device shows online within `CDM_DEVICE_ONLINE_THRESHOLD_MINUTES`

## Database testing rules

| Allowed | Avoid on shared/testing DB |
|---------|----------------------------|
| `php artisan migrate` | `migrate:fresh`, `migrate:refresh`, `db:wipe` |
| Seeders on local SQLite only | Dropping production/testing schemas |

## Pre-release regression matrix

| Area | Check |
|------|-------|
| Enrollment | Invalid/expired code rejected |
| Token | 401 without Bearer; wrong ability → 403 |
| Commands | Poll + ack idempotency |
| Location | Rejected when consent false |
| Live session | Ends when TTL exceeded |
| Backup/rollback | Restore tarball on staging |

## CI suggestions (future)

- `composer test` on PHP 8.2
- `dart analyze` + `flutter test` on stable Flutter channel
- Optional Dusk/Browser tests for admin Blade
