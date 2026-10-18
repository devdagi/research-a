# Implementation Status — Company Device Manager

**Last updated:** 2026-10-15

## Phase summary

| Phase | Name | Status |
|-------|------|--------|
| 1 | Inspect root & toolchain | **Done** |
| 2 | Research docs & architecture | **Done** |
| 3 | Laravel backend + migrations | **Done** |
| 4 | Auth, enrollment, device reporting | **Done** |
| 5 | Administrator Blade dashboard | **Done** |
| 6 | Flutter Android application | **Done** (APK build blocked) |
| 7 | Location, status, notifications | **Done** (FCM not wired) |
| 8 | Commands & consent | **Done** |
| 9 | Live-session control plane + WebRTC | **Partial** |
| 10 | Tests & builds | **Partial** |
| 11 | Deployment scripts & security docs | **Done** |

---

## Verified results (this machine)

### Toolchain

| Tool | Version / notes |
|------|-----------------|
| PHP | 8.2.12 |
| Composer | 2.9.2 |
| Laravel | 12.70.0 |
| Sanctum | 4.3.3 |
| Flutter | 3.44.7 |
| Dart | 3.12.2 |
| Java | 17 (Temurin) |
| MariaDB | 10.4.32 via XAMPP (CLI not on PATH) |
| Apache | 2.4.58 via XAMPP |
| Android SDK | **Missing** (`ANDROID_HOME` set but SDK not found) |
| nginx | Not installed locally (templates provided for EC2) |

### Backend

```
php artisan migrate   → all domain migrations applied (sqlite local)
php artisan db:seed   → admin@example.com created
php artisan test      → 14 passed (60 assertions)
composer audit        → No security vulnerability advisories found
```

### Mobile

```
flutter analyze       → 10 info-level lints (no errors)
flutter test          → 9 passed
flutter build apk     → BLOCKED: No Android SDK found
```

---

## Implemented features

### Laravel API (`/api/v1`)

- Admin session auth (Blade) with role checks (`admin`, `viewer`)
- Device enrollment via hashed, single-use, expiring codes
- Device Sanctum tokens with `device` ability (separate from admin)
- Heartbeat, status, consent-gated location
- Command queue with expiry + duplicate ack protection
- Live-session request / approve / deny / start / end
- Audit logging for sensitive actions
- Rate limiting on enroll / heartbeat / status / location
- Location retention purge command `cdm:purge-locations` (daily schedule)
- Health endpoint `GET /up`

### Admin dashboard (Blade)

Login, overview, device inventory/detail, enrollment codes (plaintext once),
commands, consents, live sessions, audit log, settings.

### Flutter Android app

Enrollment, permissions, consent, dashboard, location, status, commands,
notifications UI, live-session approval UI, privacy/unenroll.
API URL via `--dart-define=API_BASE_URL=...`.

---

## Blockers / unfinished

1. **Android SDK missing** — cannot build APK/AAB until SDK installed at `ANDROID_HOME`.
2. **WebRTC media path** — control plane works; TURN/`CDM_WEBRTC_CONFIGURED` not provisioned → live media labeled unconfigured.
3. **FCM push** — models/fields exist; Firebase project credentials not configured (polling works).
4. **Admin MFA** — schema fields present; TOTP provider not integrated.
5. **Map tiles** — location table + coordinates shown; no third-party map API key embedded.
6. **EC2 deploy** — scripts/docs ready; not executed against a live instance from this workspace.
7. **Production HTTPS / DNS** — not configured (expected).

---

## Default local credentials (change immediately)

- Email: `admin@example.com`
- Password: `ChangeMeNow!123`
