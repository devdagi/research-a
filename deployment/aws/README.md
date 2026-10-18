# Deploy CDM to AWS EC2 — `54.175.235.43`

Public URL (HTTP): **http://54.175.235.43**  
API base for Flutter: **http://54.175.235.43** (no `/cdm` suffix)

## 1. Security group (AWS console)

Allow inbound:

| Port | Source | Why |
|------|--------|-----|
| 22 | Your IP `/32` | SSH |
| 80 | `0.0.0.0/0` (or your phones’ networks) | Admin + API + Mapbox page |

Do **not** open MySQL `3306` to the internet.

## 2. Copy project to the server

From your PC (PowerShell), with your `.pem` key:

```powershell
# Replace path to key and user (ubuntu or ec2-user)
scp -i C:\path\to\key.pem -r C:\xampp\research ubuntu@54.175.235.43:/opt/cdm-src
```

Or on the server:

```bash
sudo mkdir -p /opt/cdm-src
sudo chown $USER:$USER /opt/cdm-src
git clone <YOUR_GIT_URL> /opt/cdm-src
```

## 3. Bootstrap (once)

SSH in:

```bash
ssh -i C:\path\to\key.pem ubuntu@54.175.235.43
```

```bash
cd /opt/cdm-src
chmod +x deployment/aws/bootstrap-ec2.sh deployment/scripts/*.sh
sudo CDM_REPO=/opt/cdm-src ./deployment/aws/bootstrap-ec2.sh
```

Edit Mapbox token on the server:

```bash
sudo nano /var/www/html/testing/.env
# set MAPBOX_ACCESS_TOKEN=pk....
sudo -u www-data php /var/www/html/testing/artisan config:cache
```

## 4. Verify

```bash
curl -sS http://127.0.0.1/up
curl -sS -o /dev/null -w "%{http_code}\n" http://54.175.235.43/up
```

Browser: **http://54.175.235.43/admin/login**  
Default seeder user: `admin@example.com` / `ChangeMeNow!123` (change immediately).

## 5. Redeploy after code changes

```bash
cd /opt/cdm-src
# git pull   # if using git
CONFIRM=yes ./deployment/scripts/deploy-testing.sh
sudo systemctl restart cdm-queue.service php8.2-fpm
```

## 6. Flutter app → AWS

On your PC:

```powershell
cd C:\xampp\research\mobile
.\scripts\build-apk-aws.ps1
```

Or:

```powershell
flutter run -d R9ZXA0M4PGV --dart-define=API_BASE_URL=http://54.175.235.43
```

Phone and AWS must reach each other on port 80 (same internet / open SG).

## Notes

- Local XAMPP used `http://LAN/cdm`. AWS uses the **site root** (`http://54.175.235.43`), not `/cdm`.
- HTTP is fine for lab testing; for production add HTTPS (domain + Certbot) and set `APP_URL=https://...`.
- Live audio works best on the same network or with TURN; STUN-only may fail across strict NATs.
