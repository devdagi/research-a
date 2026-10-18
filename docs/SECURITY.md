# Security

CDM manages employer-owned devices and sensitive telemetry. Design goal: **explicit consent, least privilege, auditable actions, fail closed**.

## Authentication

### Administrators

- Primary: Laravel session auth for Blade (`web` guard), CSRF on mutating forms.
- Optional API automation: Sanctum personal access tokens with scoped abilities — never reuse device tokens.
- Configure `SANCTUM_STATEFUL_DOMAINS` for hosts that use cookie-based SPA/admin access.

### Devices

- One-time enrollment codes stored **hashed**; TTL `CDM_ENROLLMENT_CODE_TTL_MINUTES`.
- Post-enrollment: Sanctum PAT in `flutter_secure_storage` (Android Keystore).
- Token abilities restrict each route (heartbeat vs commands vs live session signaling).
- Support admin-initiated revocation and re-enrollment on compromise.

See [Sanctum docs](https://laravel.com/docs/12.x/sanctum).

## Authorization

- Separate guards/abilities for admin vs device.
- Command types that touch sensors (camera, mic, location, screen) set `requires_consent`; device must record consent via `/api/v1/device/consent` before ack success.
- Live sessions require active user-visible indicators on Android; denied OS permission → session fails.

## Transport

- **Production/testing EC2:** HTTPS only for device and admin traffic; HSTS when TLS enabled.
- **SSH tunnel** recommended for early admin UI testing (`deployment/README.md`).
- Do not expose MariaDB (3306) to the public internet.

## Secrets management

- Never commit `.env`, `google-services.json`, or PATs.
- EC2 `.env` mode `600`, owned by deploy user; web server reads via PHP only.
- `CDM_TURN_USERNAME` / `CDM_TURN_CREDENTIAL` optional; prefer short-lived TURN credentials from coturn REST API.
- Backup archives contain `.env` and DB dumps — store under `/var/backups/cdm-testing` with restrictive permissions (`backup.sh` sets `600` on archives).

## Application hardening (Laravel)

- `APP_DEBUG=false` on EC2 testing/production.
- `php artisan config:cache` after deploy; do not cache debug routes in production.
- Rate limit auth and enrollment endpoints when implemented (`RateLimiter` in `AppServiceProvider`).
- Validate all device payloads; never trust client timestamps without skew checks.

## WebRTC / TURN

- Signaling over authenticated HTTPS only.
- TURN relays encrypted media; configure TURN server credentials separately from Laravel DB password.
- Optional `CDM_TURN_URLS` published to clients that already hold valid session tokens.

## Data retention

- Location rows purged after `CDM_LOCATION_RETENTION_DAYS`.
- Audit logs retained per organizational policy (longer than location).
- No default storage of live A/V media on server.

## Android client

- Follow `research/android_permissions/permissions.md` — no silent background capture.
- FCM (when added) for **alerts to poll**, not bypass consent.

## Incident response

1. Revoke device token / suspend device record.
2. Rotate `APP_KEY` only with full session invalidation plan (avoid casual rotation on testing without backup).
3. Restore from `deployment/scripts/rollback.sh` if deploy introduced regression.

## Compliance-oriented practices

- Maintain audit log for admin actions and consent changes.
- Document lawful basis and employee disclosure for corporate devices in your org’s HR/policy docs (outside this repo).
