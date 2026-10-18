# Company Device Manager — Architecture Overview

**Last verified:** 2026-10-15  
**Toolchain:** PHP 8.2.12, Laravel 12.70.0 (`laravel/laravel` v12.12.2), Sanctum 4.3.3, Flutter 3.44.7 / Dart 3.12.2, MariaDB 10.4.32 (XAMPP), Apache 2.4.58 (local dev)

## Purpose

Company Device Manager (CDM) is a corporate-owned-device platform: admins enroll and manage Android devices; devices report telemetry and execute remote commands under explicit policy and consent. The recommended split keeps **policy, audit, and persistence** on the server and **sensors, UI, and OS integration** on the mobile client.

## High-level topology

```
┌─────────────────────┐     HTTPS (session cookie)      ┌──────────────────────────┐
│  Admin (browser)    │ ───────────────────────────────►│  Laravel 12 API + Blade  │
│  Blade admin UI     │                                 │  MariaDB / Redis queue   │
└─────────────────────┘                                 └───────────┬──────────────┘
                                                                    │
                    Bearer token (Sanctum PAT, device abilities)    │
┌─────────────────────┐     HTTPS + optional WebSocket/signaling    │
│  Flutter Android    │ ◄───────────────────────────────────────────┘
│  agent app          │
└──────────┬──────────┘
           │ WebRTC media (P2P or via TURN)
           ▼
┌─────────────────────┐
│  Live session       │  Signaling + session records on Laravel; media not stored by default
│  (admin ↔ device)   │
└─────────────────────┘
```

References:

- [Laravel 12.x documentation](https://laravel.com/docs/12.x)
- [Laravel Sanctum](https://laravel.com/docs/12.x/sanctum)
- [WebRTC overview](https://webrtc.org/getting-started/overview/)
- [Flutter documentation](https://docs.flutter.dev/)

## Components

| Layer | Technology | Responsibility |
|-------|------------|----------------|
| Admin UI | Laravel Blade (+ Vite assets) | Enrollment, device fleet, command issuance, live-session initiation, audit review |
| API | Laravel 12 REST under `/api/v1` | Device enrollment, heartbeat, command queue, location uploads, session signaling |
| Auth | Laravel Sanctum (dual mode) | Admin session + optional admin API tokens; device personal access tokens with scoped abilities |
| Queue | Laravel database/Redis queue | Async command delivery acks, notifications, heavy audit writes |
| Mobile | Flutter 3.44 (Android) | Enrollment, permission UX, command execution, consent prompts, WebRTC client |
| Push (alerts only) | FCM via `firebase_messaging` | Wake/alerts for pending commands—not a substitute for location/camera consent |
| Real-time control | WebRTC + server signaling | Live view/support sessions with user-visible indicators |

## Authentication model (Sanctum dual auth)

Two credential classes must never share token stores or abilities.

### Administrators

- **Primary:** Session-based login for Blade (`web` guard), CSRF-protected forms ([session auth](https://laravel.com/docs/12.x/authentication)).
- **Optional:** Sanctum personal access tokens for automation (reporting, integrations) with abilities such as `admin:read`, `admin:write`, `admin:audit`.
- **Middleware:** `auth:sanctum` + ability checks ([token abilities](https://laravel.com/docs/12.x/sanctum#token-abilities)).

### Devices

- **Enrollment:** One-time enrollment code (hashed at rest) exchanged for a device UUID and Sanctum PAT.
- **Token abilities:** Narrow scopes, e.g. `device:heartbeat`, `device:commands:poll`, `device:location:upload`, `device:session:webrtc`.
- **Rotation:** Re-enrollment or admin-triggered token revocation replaces compromised tokens ([token expiration](https://laravel.com/docs/12.x/sanctum#token-expiration)).

## Command queue

Remote actions are **queued commands**, not silent RPC.

1. Admin creates a command record (`pending`) tied to `device_id` (UUID).
2. Device polls `GET /api/v1/devices/{id}/commands` or receives an FCM **alert** to poll sooner.
3. Device shows consent UI when required (camera, mic, live session, background location).
4. Device posts result to `POST /api/v1/commands/{id}/ack` with status, payload hash, and client timestamp.
5. Laravel queue workers process side effects (audit fan-out, admin notifications).

Use [queues](https://laravel.com/docs/12.x/queues) for anything that must survive restarts or rate spikes.

## Location: consent-based, foreground-first

- Collect location only when policy allows and the user has granted [runtime permissions](https://developer.android.com/develop/sensors-and-location/location/permissions).
- Prefer **foreground** collection with a visible notification when tracking is active ([foreground service types](https://developer.android.com/develop/background-work/services/fgs/location)).
- **Background location** is a separate permission on Android 10+ and should be rare, justified, and auditable.
- Server stores retention limits and purpose (see `research/security/decisions.md`).

## Live-session WebRTC control plane

Live support / supervised sessions use WebRTC for media; Laravel holds **signaling and audit**, not the media path by default.

| Concern | Where it lives |
|---------|----------------|
| Session create/accept/end | Laravel API + audit log |
| SDP/ICE exchange | Authenticated signaling endpoints or dedicated signaling service |
| Media | Device ↔ admin browser/app via WebRTC |
| NAT traversal | STUN + TURN ([coturn](https://github.com/coturn/coturn) or managed TURN) |

Requirements:

- Persistent user-visible indicator (camera/mic/screen) during capture.
- Session tied to admin identity and device UUID in audit tables.
- No “silent” stream; if the user denies permission, session fails closed.

See `research/implementation_decisions.md` for TURN and FCM scope notes.

## Data stores

| Store | Usage |
|-------|--------|
| MariaDB | Users, devices, enrollment codes (hashed), commands, locations (TTL), audit logs, Sanctum tokens |
| Redis (optional) | Queue, rate-limit counters, short-lived signaling state |
| Object storage (optional) | Attachment artifacts if explicitly enabled by policy |

## Local development (XAMPP)

- Apache 2.4.58 serves `backend/public` or `php artisan serve` on a dev port.
- MariaDB 10.4.32 for application schema.
- Flutter connects via `--dart-define=API_BASE_URL=http://10.0.2.2:8000` (emulator) or LAN IP on physical devices.

Production targets HTTPS on EC2/Nginx (`research/aws_deployment/ec2_testing.md`).

## Related documents

- `research/laravel_api/api_design.md` — routes and API conventions
- `research/android_permissions/permissions.md` — Android permission matrix
- `research/flutter_packages/packages.md` — mobile dependencies
- `research/security/decisions.md` — security and compliance choices
