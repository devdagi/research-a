# API

REST API under **`/api/v1`**. Implemented in `backend/routes/api.php`. Flutter paths live in `mobile/lib/core/constants.dart`.

**Auth:**

- **Devices:** `Authorization: Bearer <sanctum_pat>` with ability `device`; revoked/unenrolled devices → `403` (`device_revoked`).
- **Admins:** session cookie for Blade UI (`/admin/*`); CSRF on form POSTs.

**Format:** JSON, ISO 8601 timestamps, consistent `{ message, error, errors? }` on failures.

## Device endpoints

| Method | Path | Purpose |
|--------|------|---------|
| `POST` | `/api/v1/device/enroll` | Exchange enrollment code for UUID + PAT (throttle 10/min) |
| `GET` | `/api/v1/device/me` | Current device profile |
| `POST` | `/api/v1/device/unenroll` | Self-unenroll + invalidate tokens |
| `POST` | `/api/v1/device/heartbeat` | Update `last_seen_at` |
| `POST` | `/api/v1/device/status` | Battery / network report |
| `POST` | `/api/v1/device/location` | Location fix (**requires location consent**) |
| `GET` | `/api/v1/device/consents` | Consent history |
| `POST` | `/api/v1/device/consents` | Record grant/deny/withdraw |
| `GET` | `/api/v1/device/commands` | Poll pending/delivered commands |
| `POST` | `/api/v1/device/commands/{uuid}/ack` | Acknowledge (`status`: `acknowledged` \| `failed`) |
| `GET` | `/api/v1/device/live-sessions/pending` | Pending live-session requests |
| `POST` | `/api/v1/device/live-sessions/{uuid}/respond` | `decision`: `approved` \| `denied` |
| `POST` | `/api/v1/device/live-sessions/{uuid}/start` | Mark session active after approval |
| `POST` | `/api/v1/device/live-sessions/{uuid}/end` | End session; revoke signaling |

### Enrollment (`POST /api/v1/device/enroll`)

```json
{
  "code": "ABCD-EFGH-IJKL",
  "device_name": "Warehouse Phone",
  "platform": "android",
  "model": "Pixel 8",
  "os_version": "14",
  "fcm_token": null
}
```

`201` response:

```json
{
  "data": {
    "device": {
      "uuid": "550e8400-e29b-41d4-a716-446655440000",
      "name": "Warehouse Phone",
      "platform": "android",
      "status": "active",
      "enrolled_at": "2026-10-15T06:00:00+00:00"
    },
    "token": "1|plainTextTokenShownOnce",
    "token_type": "Bearer"
  }
}
```

Codes are hashed at rest, single-use, and expire (`CDM_ENROLLMENT_CODE_TTL_MINUTES`). Issued only from the admin UI.

### Command ack

```json
{ "status": "acknowledged", "result": { "ok": true } }
```

Duplicate acks return `duplicate: true` without error.

### Live session respond (approve)

Returns `signaling_token` (once), `webrtc_configured`, and `ice_servers`. Push notifications must **never** imply consent.

## Admin surface

Administrative actions are **session-authenticated Blade routes** under `/admin` (not a separate public JSON admin API):

- Enrollment code issue (plaintext shown once)
- Device inventory / detail / revoke
- Queue commands / request live sessions
- Consents, commands list, live sessions, audit log, settings

## Errors

| HTTP | Meaning |
|------|---------|
| 401 | Unauthenticated |
| 403 | Forbidden / revoked / missing consent |
| 409 | Invalid state (expired command/session) |
| 422 | Validation failed |

## Health

`GET /up`
