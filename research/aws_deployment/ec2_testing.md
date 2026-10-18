# AWS EC2 Testing Deployment — Company Device Manager

**Last verified:** 2026-10-15  
**Target:** Ubuntu LTS on EC2, Nginx, PHP 8.2+, Laravel 12 backend at **`/var/www/html/testing`**

References:

- [Amazon EC2 User Guide](https://docs.aws.amazon.com/ec2/latest/userguide/)
- [Nginx documentation](https://nginx.org/en/docs/)
- [Laravel deployment](https://laravel.com/docs/12.x/deployment)
- [Ubuntu server guide](https://ubuntu.com/server/docs)

## Goals

- Host a **testing** stack reachable by IP or SSH tunnel without public DNS.
- Expose only HTTP/HTTPS and SSH; **no public MySQL/MariaDB**.
- Use **non-destructive** deploy scripts (no `rm -rf`, no dropping databases without explicit flag).

## Instance layout

```
/var/www/html/testing/          # Laravel application root (backend)
├── current -> releases/...     # optional symlink pattern
├── shared/.env                 # shared env (not in git)
├── storage/                    # linked shared storage
└── public/                     # Nginx document root
```

For initial testing, a single directory `/var/www/html/testing` matching a git checkout is sufficient.

## Security groups

| Rule | Port | Source | Purpose |
|------|------|--------|---------|
| Inbound TCP | 22 | Your office IP `/32` | SSH administration |
| Inbound TCP | 80 | Your office IP or 0.0.0.0/0 (testing only) | HTTP redirect or ACME |
| Inbound TCP | 443 | Same as 80 | HTTPS API + admin |
| **Deny** | 3306 | 0.0.0.0/0 | MySQL not public |
| **Deny** | 3306 | ::/0 | MariaDB not public |

Reference: [Security groups for EC2](https://docs.aws.amazon.com/vpc/latest/userguide/vpc-security-groups.html).

Database listens on `127.0.0.1` only. Admins reach DB via **SSH tunnel** (below).

## Nginx server block (template)

Replace `TESTING_SERVER_NAME` with EC2 public IP or private hostname.

```nginx
# /etc/nginx/sites-available/cdm-testing
server {
    listen 80;
    listen [::]:80;
    server_name TESTING_SERVER_NAME;

    root /var/www/html/testing/public;
    index index.php;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

Enable site:

```bash
sudo ln -s /etc/nginx/sites-available/cdm-testing /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
```

HTTPS: use [Certbot with Nginx](https://certbot.eff.org/instructions) when a domain exists; for IP-only testing, prefer SSH tunnel or self-signed cert **only** on private networks.

## PHP and permissions

```bash
sudo chown -R www-data:www-data /var/www/html/testing/storage /var/www/html/testing/bootstrap/cache
sudo chmod -R ug+rwx storage bootstrap/cache
```

Run migrations as deploy user:

```bash
cd /var/www/html/testing
php artisan migrate --force
php artisan config:cache
php artisan route:cache
```

Set production-safe env ([deployment](https://laravel.com/docs/12.x/deployment#optimization)):

- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_URL=https://TESTING_SERVER_NAME`

## SSH tunnel (no DNS)

Access admin UI and API from laptop without opening DB:

```bash
# Terminal 1 — forward HTTPS to local browser
ssh -i key.pem -L 8443:127.0.0.1:443 ubuntu@EC2_PUBLIC_IP

# Terminal 2 — optional MySQL tunnel for debugging
ssh -i key.pem -L 3307:127.0.0.1:3306 ubuntu@EC2_PUBLIC_IP
```

Browse `https://localhost:8443` (accept cert warning if self-signed). Flutter physical devices cannot use localhost — use EC2 public IP on 443 with proper cert or VPN.

## Non-destructive deploy script pattern

Scripts should:

1. `git fetch` + `git checkout` tagged commit (no hard reset to unknown refs).
2. `composer install --no-dev --optimize-autoloader`
3. `php artisan migrate --force` (forward-only migrations)
4. `php artisan queue:restart`
5. Skip `db:wipe`, `migrate:fresh`, or `rm -rf storage/*` unless `CDM_ALLOW_DESTRUCTIVE=1` is set.

Example guard:

```bash
if [ "${CDM_ALLOW_DESTRUCTIVE:-0}" != "1" ]; then
  echo "Destructive commands disabled. Set CDM_ALLOW_DESTRUCTIVE=1 to override."
fi
```

## Queue worker (systemd)

```ini
# /etc/systemd/system/cdm-testing-queue.service
[Unit]
Description=CDM testing queue worker
After=network.target

[Service]
User=www-data
WorkingDirectory=/var/www/html/testing
ExecStart=/usr/bin/php artisan queue:work --sleep=3 --tries=3 --max-time=3600
Restart=always

[Install]
WantedBy=multi-user.target
```

Reference: [Laravel queues in production](https://laravel.com/docs/12.x/queues#supervisor-configuration).

## Flutter testing against EC2

```bash
flutter run --dart-define=API_BASE_URL=https://EC2_PUBLIC_IP
```

Ensure Sanctum/CORS and `APP_URL` match the HTTPS origin mobile uses.

## Checklist

- [ ] Security group: 22/80/443 only; DB bound to localhost
- [ ] `APP_DEBUG=false`, strong `APP_KEY`
- [ ] TLS on 443 for real devices
- [ ] Queue worker running
- [ ] Audit log table migrated
- [ ] Backup snapshot before first production-like test

## Related documents

- `research/security/decisions.md`
- `research/laravel_api/api_design.md`
