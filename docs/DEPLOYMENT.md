# Deployment

Operational guide for CDM testing on EC2 and parity with local XAMPP development.

**Deploy root:** `/var/www/html/testing`  
**Source in repo:** `backend/`  
**Detailed EC2 steps:** `deployment/README.md`

## Environments

| Environment | APP_ENV | APP_DEBUG | Notes |
|-------------|---------|-----------|-------|
| Local (XAMPP) | `local` | `true` | SQLite or local MariaDB |
| EC2 testing | `production` or `staging` | `false` | Nginx + PHP-FPM + MariaDB |

## Pre-deploy

1. Run `deployment/scripts/preflight-check.sh` on the target host.
2. Create MariaDB database and user (`docs/DATABASE.md`).
3. Copy `backend/.env.example` → `/var/www/html/testing/.env` and set:
   - `APP_URL` (public or tunneled URL)
   - `DB_*` for MariaDB
   - `CDM_*` policy variables
   - `QUEUE_CONNECTION=database`
4. Install Nginx site from `deployment/nginx/testing.conf` (**additional** site; keep default).

## Deploy command

From repository on server:

```bash
chmod +x deployment/scripts/*.sh
CONFIRM=yes ./deployment/scripts/deploy-testing.sh
```

Environment overrides:

| Variable | Default |
|----------|---------|
| `CDM_SOURCE` | `<repo>/backend` |
| `CDM_DEPLOY_PATH` | `/var/www/html/testing` |
| `CDM_WEB_USER` / `CDM_WEB_GROUP` | `www-data` |
| `CDM_BACKUP_ROOT` | `/var/backups/cdm-testing` |

Deploy performs **`php artisan migrate --force`** only — never `migrate:fresh`.

## Systemd services

| Unit | Role |
|------|------|
| `cdm-queue.service` | `queue:work database` |
| `cdm-scheduler.timer` | `schedule:run` each minute |

```bash
sudo cp deployment/systemd/* /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable --now cdm-queue.service cdm-scheduler.timer
```

## Post-deploy

```bash
cd /var/www/html/testing
php artisan about
sudo systemctl restart php8.2-fpm
sudo systemctl restart cdm-queue.service
```

Verify health: `GET /up`.

## Backups

Schedule via cron (example daily 02:00):

```cron
0 2 * * * root /opt/cdm-src/deployment/scripts/backup.sh >> /var/log/cdm-backup.log 2>&1
```

## Rollback

```bash
CONFIRM=yes ./deployment/scripts/rollback.sh
```

Restores `.env`, `storage/`, and SQL dump from archive — does not rsync code; redeploy previous git tag if code rollback needed.

## Local setup helpers

| OS | Script |
|----|--------|
| Windows | `scripts/local-setup.ps1` |
| Linux/macOS | `scripts/local-setup.sh` |

## Mobile builds pointing at testing

Build APK with API URL (no repo changes required):

```bash
flutter build apk --dart-define=API_BASE_URL=https://your-testing-host
```

**AWS EC2 (current lab IP):** see `deployment/aws/README.md` — host `54.175.235.43`.

```powershell
cd mobile
.\scripts\build-apk-aws.ps1
# or: flutter run --dart-define=API_BASE_URL=http://54.175.235.43
```

Use HTTPS in all non-local scenarios when a domain is available.

## TLS

Uncomment SSL block in `deployment/nginx/testing.conf` or terminate TLS at ALB. Update `APP_URL` to `https://...` and Sanctum stateful domains if using cookie auth.
