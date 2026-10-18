# Laravel API Design — Company Device Manager

**Last verified:** 2026-10-15  
**Stack:** Laravel 12.70.0, Sanctum 4.3.3, PHP 8.2.12

References:

- [Routing](https://laravel.com/docs/12.x/routing)
- [Sanctum](https://laravel.com/docs/12.x/sanctum)
- [Rate limiting](https://laravel.com/docs/12.x/routing#rate-limiting)
- [Validation](https://laravel.com/docs/12.x/validation)
- [Eloquent UUIDs](https://laravel.com/docs/12.x/eloquent#uuid-and-ulid-keys)

## Versioning and base path

All JSON endpoints live under **`/api/v1`**. Breaking changes require `/api/v2`; clients send:

```http
Accept: application/json
Content-Type: application/json
```

Register routes in `routes/api.php` with prefix `v1` ([route groups](https://laravel.com/docs/12.x/routing#route-groups)).

## Authentication

| Client | Guard / mechanism | Typical routes |
|--------|-------------------|----------------|
| Blade admin (session) | `web` + Sanctum SPA optional | Admin pages, form posts |
| Admin API token | Sanctum PAT, abilities `admin:*` | Automation, reporting |
| Device agent | Sanctum PAT, abilities `device:*` | Poll, ack, telemetry |

Device tokens are created only via enrollment exchange; admins use separate credentials ([Sanctum abilities](https://laravel.com/docs/12.x/sanctum#token-abilities)).

### Ability examples

```
admin:devices:read
admin:devices:write
admin:commands:issue
admin:audit:read
device:enroll
device:heartbeat
device:commands:poll
device:commands:ack
device:location:upload
device:session:signaling
```

Middleware pattern: `auth:sanctum` + custom `CheckAbilities` or Sanctum’s `ability:` middleware ([protecting routes](https://laravel.com/docs/12.x/sanctum#protecting-routes)).

## Device identity: UUID

- Primary key for devices exposed to clients: **UUID** (`char(36)` or native UUID column).
- Use Laravel’s `HasUuids` trait or ULID if sortable IDs are needed ([UUID keys](https://laravel.com/docs/12.x/eloquent#uuid-and-ulid-keys)).
- Never expose sequential integer IDs to mobile clients.

## Enrollment codes (hashed)

Flow:

1. Admin creates enrollment record → server generates high-entropy code (display once).
2. Store **only** `Hash::make($code)` (bcrypt/argon) — same pattern as passwords ([Hashing](https://laravel.com/docs/12.x/hashing)).
3. Device submits code + device metadata → on success, issue UUID + Sanctum token with `device:*` abilities.
4. Invalidate or expire single-use codes; rate-limit verification attempts.

```http
POST /api/v1/enroll
{
  "enrollment_code": "XXXX-XXXX-XXXX",
  "device_name": "Pixel 8 - Warehouse",
  "app_version": "1.0.0",
  "os_version": "14"
}
```

Response (201):

```json
{
  "device_id": "9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d",
  "token": "1|plainTextTokenOnlyShownOnce",
  "token_type": "Bearer",
  "abilities": ["device:heartbeat", "device:commands:poll"]
}
```

## Rate limiting

Define named limiters in `AppServiceProvider` or dedicated provider ([rate limiting](https://laravel.com/docs/12.x/routing#defining-rate-limiters)):

| Limiter | Scope | Suggested limit |
|---------|--------|-----------------|
| `enroll` | IP + code fingerprint | 5/minute |
| `device-poll` | device UUID | 60/minute |
| `admin-api` | admin user id | 120/minute |
| `location-upload` | device UUID | 30/minute |

Apply via `throttle:enroll` middleware on routes.

## Audit logs

Append-only `audit_logs` table (or dedicated database):

| Column | Purpose |
|--------|---------|
| `id` | Big integer PK |
| `occurred_at` | UTC timestamp |
| `actor_type` / `actor_id` | Admin user or device UUID |
| `action` | e.g. `command.issued`, `enrollment.redeemed`, `session.started` |
| `subject_type` / `subject_id` | Device, command, session |
| `ip_address` | Request IP |
| `user_agent` | Client string |
| `metadata` | JSON (redacted — no raw tokens) |

Emit audits synchronously for security events; queue for high-volume telemetry if needed ([events](https://laravel.com/docs/12.x/events)).

## Core resources (v1)

### Admin (auth required)

| Method | Path | Description |
|--------|------|-------------|
| GET | `/api/v1/devices` | Paginated fleet list |
| POST | `/api/v1/devices/enrollment-codes` | Create hashed enrollment code |
| GET | `/api/v1/devices/{device}` | Device detail + last heartbeat |
| POST | `/api/v1/devices/{device}/commands` | Issue command (queued) |
| GET | `/api/v1/audit-logs` | Filterable audit stream |

### Device (Sanctum device token)

| Method | Path | Description |
|--------|------|-------------|
| POST | `/api/v1/enroll` | Redeem enrollment code |
| POST | `/api/v1/devices/{device}/heartbeat` | Battery, connectivity, app version |
| GET | `/api/v1/devices/{device}/commands` | Poll pending commands |
| POST | `/api/v1/commands/{command}/ack` | Ack success/failure + payload |
| POST | `/api/v1/devices/{device}/locations` | Consent-gated location batch |
| POST | `/api/v1/sessions/{session}/signaling` | WebRTC SDP/ICE messages |

Use [API resources](https://laravel.com/docs/12.x/eloquent-resources) for stable JSON shapes.

## Command model

States: `pending` → `delivered` → `completed` | `failed` | `expired`.

```json
{
  "id": "cmd_01H...",
  "type": "location.request",
  "requires_consent": true,
  "payload": { "max_age_seconds": 300 },
  "expires_at": "2026-10-15T12:00:00Z"
}
```

Server validates device ownership: route `{device}` must match authenticated token’s device id.

## Error format

Consistent JSON errors ([HTTP responses](https://laravel.com/docs/12.x/responses)):

```json
{
  "message": "The given data was invalid.",
  "errors": { "enrollment_code": ["Invalid or expired code."] }
}
```

Use 401 for auth, 403 for ability mismatch, 429 for throttle, 422 for validation.

## Local configuration

- `SANCTUM_STATEFUL_DOMAINS` for Blade admin origin if using cookie SPA hybrid ([Sanctum config](https://laravel.com/docs/12.x/sanctum#configuration)).
- `APP_URL` must match issued URLs in non-production.
- Queue driver: `database` for XAMPP simplicity; `redis` in production.

## Related documents

- `research/architecture/overview.md`
- `research/security/decisions.md`
- `research/aws_deployment/ec2_testing.md`
