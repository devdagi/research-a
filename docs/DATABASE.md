# Database

**Engine:** MariaDB 10.4+ (XAMPP / EC2) or SQLite for local bootstrap  
**Laravel:** `DB_CONNECTION=sqlite` by default in `.env`; use `mysql` on testing EC2.

## Migrations present

| Migration | Tables |
|-----------|--------|
| `0001_01_01_000000_create_users_table` | `users` (+ `role`, `mfa_enabled`, `mfa_secret`), `password_reset_tokens`, `sessions` |
| `0001_01_01_000001_create_cache_table` | `cache`, `cache_locks` |
| `0001_01_01_000002_create_jobs_table` | `jobs`, `job_batches`, `failed_jobs` |
| `2024_01_01_000010_create_devices_table` | `devices` |
| `2024_01_01_000020_create_device_enrollment_codes_table` | `device_enrollment_codes` |
| `2024_01_01_000030_create_device_statuses_table` | `device_statuses` |
| `2024_01_01_000040_create_device_locations_table` | `device_locations` |
| `2024_01_01_000050_create_device_consents_table` | `device_consents` |
| `2024_01_01_000060_create_device_commands_table` | `device_commands` |
| `2024_01_01_000070_create_live_sessions_table` | `live_sessions` |
| `2024_01_01_000080_create_audit_logs_table` | `audit_logs` |
| `2024_01_01_000090_create_personal_access_tokens_table` | `personal_access_tokens` |
| `2024_01_01_000100_create_app_settings_table` | `app_settings` |

```bash
cd backend
php artisan migrate
php artisan db:seed
```

**Never** run `migrate:fresh` / `migrate:refresh` on shared testing or production.

## Domain notes

### `devices`

- Public id: `uuid` (route key)
- `status`: `active` \| `revoked` \| `unenrolled`
- Online derived from `last_seen_at` vs `CDM_DEVICE_ONLINE_THRESHOLD_MINUTES`

### `device_enrollment_codes`

- Stores `code_hash` (SHA-256 of normalized plaintext) — **never** plaintext
- Single-use (`used_at`, `used_by_device_id`), `expires_at`, `attempt_count`

### `device_commands`

- Public id: `uuid`
- Optional unique `(device_id, idempotency_key)`
- Statuses: `pending`, `delivered`, `acknowledged`, `failed`, `expired`, `cancelled`

### `live_sessions`

- Public id: `uuid`
- `signaling_token_hash` set on approve; cleared on end/cancel
- Statuses: `pending`, `approved`, `denied`, `active`, `ended`, `expired`, `cancelled`

### `device_locations`

- Purged by `php artisan cdm:purge-locations` using `CDM_LOCATION_RETENTION_DAYS`

## Seeded admin

- Email: `admin@example.com`
- Password: `ChangeMeNow!123` — **change immediately**
