# CDM Testing Deployment (EC2)

Safe deployment guide for **Company Device Manager** on a dedicated testing path: `/var/www/html/testing`. This layout keeps the default Nginx/Apache site intact and isolates CDM behind its own vhost.

**Stack:** Laravel 12, Sanctum 4.3, PHP 8.2, MariaDB, Nginx + PHP-FPM, optional Redis later.

## Prerequisites

- Ubuntu 22.04+ or Amazon Linux 2023 EC2 instance
- PHP 8.2 with extensions: `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `fileinfo`, `bcmath`
- Composer 2.x, MariaDB 10.6+, Nginx, PHP-FPM (`php8.2-fpm`)
- Security group allowing **SSH (22)** from your IP only; **HTTP (80)** from office/VPN or tunneled (see below)

Run preflight locally on the server:

```bash
chmod +x deployment/scripts/*.sh
./deployment/scripts/preflight-check.sh
```

## Directory layout on server

| Path | Purpose |
|------|---------|
| `/var/www/html/testing` | Laravel app root (`public/` is web root) |
| `/var/backups/cdm-testing` | Backups from `backup.sh` |
| `/etc/nginx/sites-available/cdm-testing.conf` | CDM Nginx site (copy from `deployment/nginx/testing.conf`) |

## Nginx (do not overwrite default site)

```bash
sudo cp deployment/nginx/testing.conf /etc/nginx/sites-available/cdm-testing.conf
sudo ln -sf /etc/nginx/sites-available/cdm-testing.conf /etc/nginx/sites-enabled/cdm-testing.conf
sudo nginx -t && sudo systemctl reload nginx
```

Adjust `fastcgi_pass` if your PHP-FPM socket differs (e.g. Amazon Linux: `/run/php-fpm/www.sock`).

TLS: uncomment the SSL server block in `testing.conf` and point certificates at Let's Encrypt or ACM-imported certs.

## Systemd (queue + scheduler)

```bash
sudo cp deployment/systemd/cdm-queue.service /etc/systemd/system/
sudo cp deployment/systemd/cdm-scheduler.service deployment/systemd/cdm-scheduler.timer /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable --now cdm-queue.service
sudo systemctl enable --now cdm-scheduler.timer
```

Verify:

```bash
sudo systemctl status cdm-queue.service
sudo systemctl list-timers cdm-scheduler.timer
```

## Deploy application

1. Clone or rsync this repository to the server (e.g. `/opt/cdm-src`).
2. Configure `/var/www/html/testing/.env` for MariaDB, `APP_URL`, and `CDM_*` settings (see `backend/.env.example`).
3. Deploy:

```bash
CONFIRM=yes ./deployment/scripts/deploy-testing.sh
```

The script:

- Runs `preflight-check.sh`
- Backs up existing `.env`, `storage/`, and DB if present
- Syncs `backend/` → `/var/www/html/testing`
- Runs `composer install --no-dev`, `php artisan migrate --force` (never `migrate:fresh`)
- Caches config/routes/views and fixes permissions

Override source path: `CDM_SOURCE=/opt/cdm-src/backend CONFIRM=yes ./deployment/scripts/deploy-testing.sh`

## Backup and rollback

```bash
./deployment/scripts/backup.sh
CONFIRM=yes ./deployment/scripts/rollback.sh   # uses latest archive
CONFIRM=yes ./deployment/scripts/rollback.sh /var/backups/cdm-testing/cdm-testing-YYYYMMDD-HHMMSS.tar.gz
```

## SSH tunnel (recommended for testing)

Avoid exposing admin UI to the public internet during early testing:

1. Security group: allow **SSH** from your IP; **do not** open 80/443 to `0.0.0.0/0` unless required.
2. From your workstation:

```bash
ssh -i your-key.pem -L 8080:127.0.0.1:80 ec2-user@<instance-public-ip>
```

3. Browse `http://127.0.0.1:8080` (hits Nginx on the instance loopback).

For HTTPS tunnel: `-L 8443:127.0.0.1:443`.

## Security groups (summary)

| Rule | Source | Notes |
|------|--------|-------|
| TCP 22 | Your office/home IP | SSH admin |
| TCP 80/443 | VPN CIDR or disabled | Use SSH tunnel first |
| TCP 3306 | **Never** public | MariaDB on localhost or private subnet only |

Mobile devices need reachable HTTPS for production; for lab testing, use VPN or a known test hostname with TLS.

## Post-deploy checks

```bash
curl -sS -o /dev/null -w "%{http_code}\n" http://127.0.0.1/up
cd /var/www/html/testing && php artisan about
sudo journalctl -u cdm-queue.service -n 50 --no-pager
```

## Related documentation

- `docs/DEPLOYMENT.md` — operational runbook
- `docs/TROUBLESHOOTING.md` — common failures
- `research/architecture/overview.md` — system design
