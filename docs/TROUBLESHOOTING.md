# Troubleshooting

Common issues for CDM Laravel backend, EC2 testing deploy, and Flutter connectivity.

## Deploy / preflight

### `preflight-check.sh` exits 1 — app conflict

**Symptom:** Error about WordPress or non-Laravel app at `/var/www/html/testing`.

**Fix:** Choose a clean path or remove foreign content after backup. CDM expects Laravel `artisan` at deploy root.

### `CONFIRM=yes` required

**Symptom:** Deploy or rollback refuses to run.

**Fix:** Intentional guard — run `CONFIRM=yes ./deployment/scripts/deploy-testing.sh`.

### Composer fails on server

**Symptom:** Missing extensions during `composer install`.

**Fix:** Install `php8.2-mysql`, `php8.2-mbstring`, `php8.2-xml`, `php8.2-curl`, etc.; re-run preflight.

## Nginx / PHP-FPM

### 502 Bad Gateway

**Causes:** PHP-FPM stopped; wrong socket path.

**Fix:**

```bash
sudo systemctl status php8.2-fpm
# Compare socket with fastcgi_pass in /etc/nginx/sites-enabled/cdm-testing.conf
sudo nginx -t && sudo systemctl reload nginx
```

Amazon Linux may use `/run/php-fpm/www.sock` instead of `/run/php/php8.2-fpm.sock`.

### 404 on all routes except `/`

**Symptom:** Laravel routes fail; static files work.

**Fix:** Ensure `root` points to `/var/www/html/testing/public` and `try_files` includes `/index.php`.

### 500 after deploy

**Fix:**

```bash
cd /var/www/html/testing
php artisan config:clear
tail -n 50 storage/logs/laravel.log
```

Check `storage/` and `bootstrap/cache` writable by `www-data`.

## Database

### `SQLSTATE[HY000] [1045] Access denied`

**Fix:** Verify `.env` `DB_*` matches MariaDB user/host; `mysql -u cdm_testing -p cdm_testing`.

### Migration errors

**Fix:** Read migration output; resolve forward — **do not** run `migrate:fresh` on testing EC2. Restore from backup if needed.

## Queue / scheduler

### Commands stuck pending

**Symptom:** Device polls but side effects never run.

**Fix:**

```bash
sudo systemctl status cdm-queue.service
sudo journalctl -u cdm-queue.service -n 100
cd /var/www/html/testing && php artisan queue:failed
```

Ensure `QUEUE_CONNECTION=database` and `jobs` table exists.

### Scheduled tasks not running

**Fix:**

```bash
systemctl list-timers | grep cdm-scheduler
sudo systemctl start cdm-scheduler.timer
```

## Sanctum / API

### 401 on device routes

**Causes:** Missing `Authorization: Bearer`; revoked token; clock skew.

**Fix:** Re-enroll device; confirm `personal_access_tokens` row exists.

### 419 / CSRF on admin forms

**Fix:** Session domain and `APP_URL` must match browser URL; add host to `SANCTUM_STATEFUL_DOMAINS`.

## Flutter client

### Cannot reach API from emulator

**Fix:** Use `http://10.0.2.2:8000` for host machine `artisan serve`. For EC2, use public HTTPS or VPN — not `localhost` on device.

### Cleartext HTTP blocked (Android 9+)

**Fix:** Use HTTPS on testing server or network security config for **debug builds only**.

### WebRTC connects but no media

**Fix:** Configure TURN (`CDM_TURN_URLS` + dart-define `TURN_*`); verify camera/mic permissions on device.

## Backups / rollback

### `mysqldump` permission denied in backup.sh

**Fix:** Grant SELECT on DB to backup user or run backup as DBA; ensure `.env` readable to script operator.

### Rollback SQL fails

**Fix:** Confirm dump matches current schema version; restore code to same git tag as backup if migrations diverged.

## Logs

| Component | Location |
|-----------|----------|
| Laravel | `/var/www/html/testing/storage/logs/laravel.log` |
| Nginx | `/var/log/nginx/error.log` |
| Queue worker | `journalctl -u cdm-queue.service` |
| Scheduler | `journalctl -u cdm-scheduler.service` |

## Getting help

Collect: `php artisan about`, preflight output, relevant log excerpts (redact tokens), and deploy command used.
