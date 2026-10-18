# Company Device Manager — Backend

Laravel 12 API and Blade admin dashboard for company-owned Android device management.

## Requirements

- PHP 8.2+
- Composer 2
- SQLite (local default) or MySQL/MariaDB

## Quick start (local)

```bash
cd backend
cp .env.example .env   # if needed
php artisan key:generate
php artisan migrate
php artisan db:seed
php artisan serve
```

Admin UI: http://127.0.0.1:8000/admin/login

Default seeded admin (change immediately):

- Email: `admin@example.com`
- Password: `ChangeMeNow!123`

API base: `http://127.0.0.1:8000/api/v1`

## Tests

```bash
php artisan test
```

## Health

`GET /up`
