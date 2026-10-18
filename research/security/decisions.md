# Security Decisions — Company Device Manager

**Last verified:** 2026-10-15

This document records security choices for CDM. It complements Laravel and Android platform guidance:

- [Laravel security](https://laravel.com/docs/12.x/security)
- [Sanctum](https://laravel.com/docs/12.x/sanctum)
- [Android privacy best practices](https://developer.android.com/privacy)

## 1. Separate admin vs device credentials

| Aspect | Administrators | Devices |
|--------|----------------|---------|
| Identity | User account (email + password, optional MFA) | Device UUID |
| Auth | Session (Blade) and/or Sanctum PAT | Sanctum PAT only |
| Storage | Server session + httpOnly cookies | `flutter_secure_storage` on device |
| Abilities | `admin:*` scopes | `device:*` scopes only |

**Decision:** Never issue `admin:*` abilities to device tokens. Admin actions always attributable to a user id in audit logs.

## 2. Hashed enrollment codes

- Enrollment codes are **single-use or short-lived**, generated with CSPRNG.
- Persist **hashes** only (`Hash::make`), never plaintext ([hashing](https://laravel.com/docs/12.x/hashing)).
- Brute-force protection: rate limiting + lockout after repeated failures (`research/laravel_api/api_design.md`).

## 3. HTTPS in production

- All API and admin traffic over TLS 1.2+.
- `APP_URL` uses `https://`; HSTS enabled at Nginx when stable domain exists.
- Mobile pins optional; at minimum enforce HTTPS and certificate validation in release builds.

**Local XAMPP:** HTTP acceptable on loopback/LAN only; never ship `http://` production URLs in release APKs.

## 4. Production hardening

| Setting | Value |
|---------|--------|
| `APP_DEBUG` | `false` ([configuration](https://laravel.com/docs/12.x/configuration)) |
| `APP_ENV` | `production` |
| Error pages | Generic; log details server-side only |
| `.env` | Not in git; restricted permissions on server |

Run `php artisan config:cache` and `route:cache` on deploy.

## 5. Consent and live-session audit

Every live session or sensitive command records:

- Admin user id initiating action
- Device UUID
- Command/session type and timestamps (start, end, ack)
- Client-reported permission state (no bypass)
- IP and user agent

**Decision:** If consent UI is declined, store `failed` with reason `consent_denied` — do not retry in a loop.

WebRTC signaling metadata may be logged; **media streams are not recorded** unless a separate explicit policy and UI toggle exists (out of initial scope).

## 6. MFA integration notes

Laravel 12 does not include MFA in core; integrate when admin accounts are exposed to the internet:

| Option | Notes |
|--------|--------|
| [Laravel Fortify](https://laravel.com/docs/12.x/fortify) | TOTP, recovery codes |
| SAML/OIDC | Enterprise IdP for admin SSO |
| Hardware keys | WebAuthn via Fortify or custom |

**Recommendation:** Require MFA for admin accounts in production; device agents use token + device possession, not user MFA.

## 7. Location retention

| Policy | Default |
|--------|---------|
| Purpose | Workforce safety / assigned duty verification only |
| Retention | 90 days rolling (tenant-configurable) |
| Minimization | Store lat/lng accuracy, timestamp, command id — not continuous history unless policy enables |
| Deletion | Automated job purges rows past retention; audit entry for policy changes |

Align collection with [Android location permission tiers](https://developer.android.com/develop/sensors-and-location/location/permissions) and employee disclosure.

## 8. Token lifecycle

- Device tokens revocable from admin console ([token revocation](https://laravel.com/docs/12.x/sanctum#revoking-tokens)).
- Rotate on re-enrollment; old tokens invalidated immediately.
- Optional expiration per tenant compliance.

## 9. Logging and secrets

- Redact tokens, enrollment codes, and passwords from logs ([logging](https://laravel.com/docs/12.x/logging)).
- Use structured logging for audit vs application logs.
- FCM server keys and AWS credentials in env/secret manager only.

## 10. Dependency and patch cadence

- `composer update` / security advisories for Laravel ([releases](https://laravel.com/docs/12.x/releases)).
- Flutter/Android security patches monthly minimum.

## Related documents

- `research/architecture/overview.md`
- `research/implementation_decisions.md`
- `research/aws_deployment/ec2_testing.md`
