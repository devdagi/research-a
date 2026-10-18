# Architecture

**Company Device Manager (CDM)** — corporate-owned Android device management with a Laravel control plane, Sanctum-authenticated APIs, and WebRTC live-session control plane.

**Last verified:** 2026-10-15  
**Repository layout:** `backend/` (Laravel 12), `mobile/` (Flutter Android agent), `research/` (design notes).

## Current implementation status

| Area | Status |
|------|--------|
| Laravel 12 + Sanctum 4.3 | **Done** — API + Blade admin |
| `/api/v1` device API | **Done** — see `backend/routes/api.php` |
| Admin Blade UI | **Done** — `/admin/*` |
| Domain schema | **Done** — devices, enrollment codes, locations, statuses, consents, commands, live sessions, audit logs, settings |
| Queue / scheduler | Jobs table present; `cdm:purge-locations` scheduled daily; EC2 systemd units in `deployment/` |
| WebRTC media / TURN | Control plane **done**; media path needs TURN (`CDM_WEBRTC_CONFIGURED`) |
| FCM push | Field/plumbing only — devices poll commands |

## Topology

```
 Admin browser ──HTTPS(session)──► Laravel (Blade + /api/v1)
                                        │
                                        ├── MariaDB / SQLite
                                        ├── Queue (database)
                                        └── Live-session signaling credentials

 Flutter Android ──HTTPS(Bearer PAT)──► same Laravel API
        │
        └── WebRTC media ◄──► Admin viewer (requires TURN when configured)
```

## Components

### Backend (`backend/`)

- **Framework:** Laravel 12, PHP 8.2
- **Auth:** Laravel Sanctum dual mode:
  - **Admins:** session (`web` guard) for Blade; roles `admin` / `viewer`
  - **Devices:** Sanctum PATs on `Device` model with ability `device`
- **Health:** `GET /up`
- **Retention:** `php artisan cdm:purge-locations` (daily via scheduler)
- **Async:** `QUEUE_CONNECTION=database`; EC2 `cdm-queue.service`

### Mobile (`mobile/`)

- Flutter 3.44 / Dart 3.12
- `--dart-define=API_BASE_URL=...` (default emulator host `http://10.0.2.2:8000`)
- Paths under `/api/v1/device/*` aligned with Laravel routes
- No silent capture — employee must approve live sessions

### Real-time (WebRTC)

- Backend issues short-lived signaling token on **approve**
- Media not recorded by default
- Set `CDM_WEBRTC_CONFIGURED=true` and TURN vars when provisioned

## Command flow

1. Admin queues command from device detail page.
2. Device polls `GET /api/v1/device/commands` (FCM optional later).
3. Device acks via `POST /api/v1/device/commands/{uuid}/ack`.
4. Expired commands marked `expired`; duplicate acks are idempotent.

## Configuration

| Variable | Purpose |
|----------|---------|
| `CDM_DEVICE_ONLINE_THRESHOLD_MINUTES` | Offline detection |
| `CDM_LOCATION_RETENTION_DAYS` | Location TTL |
| `CDM_ENROLLMENT_CODE_TTL_MINUTES` | Enrollment code expiry |
| `CDM_LIVE_SESSION_TTL_MINUTES` | Live session request TTL |
| `CDM_TURN_URLS` / `CDM_TURN_*` | ICE TURN |
| `CDM_WEBRTC_CONFIGURED` | Marks media path ready |

## Deployment

Testing EC2: `/var/www/html/testing` — see `deployment/README.md` and `docs/DEPLOYMENT.md`.
