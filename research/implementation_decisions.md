# Implementation Decisions — Company Device Manager

**Last verified:** 2026-10-15  
**Date checked:** 2026-10-15

## Chosen stack (summary)

| Layer | Choice | Version (verified) |
|-------|--------|---------------------|
| API / admin | Laravel | 12.70.0 (`laravel/laravel` skeleton v12.12.2) |
| API auth | Laravel Sanctum | 4.3.3 |
| Runtime | PHP | 8.2.12 (Composer 2.9.2) |
| Mobile | Flutter | 3.44.7 (Dart 3.12.2) |
| Android build | Java | 17 (Temurin) |
| Local DB / web | MariaDB 10.4.32, Apache 2.4.58 (XAMPP) |
| Testing deploy | Ubuntu EC2 + Nginx | `/var/www/html/testing` |

## Why Laravel 12 (not 13)

- Project targets **PHP 8.2.12** on XAMPP and EC2 PHP 8.2 images.
- Laravel 13.x requires **PHP 8.3+** per [Laravel release notes](https://laravel.com/docs/12.x/releases#support-policy) and upgrade guides — upgrading PHP on all dev machines and servers is a separate project.
- Laravel 12 is the current LTS-track release for PHP 8.2 and matches installed `laravel/framework` v12.70.0.

**Decision:** Stay on Laravel 12 until PHP 8.3+ is standardized everywhere, then plan a coordinated framework upgrade.

## Why Sanctum for dual auth

Single package covers:

1. **Admin:** Session-based Blade auth with CSRF; optional PAT for scripts ([Sanctum intro](https://laravel.com/docs/12.x/sanctum)).
2. **Devices:** Long-lived API tokens with **abilities** per device (`device:commands:poll`, etc.).

Alternatives considered:

| Option | Rejected because |
|--------|------------------|
| Passport | Heavier OAuth2 surface; unnecessary for first-party mobile agent |
| Custom JWT | More key rotation and revocation logic to maintain |
| Shared admin/device tokens | Violates least privilege |

Sanctum is already installed and published in `backend/` (`config/sanctum.php`, personal access tokens migration).

## Why WebRTC for live media

Live admin↔device sessions need low-latency audio/video with user-visible indicators.

- **WebRTC** ([overview](https://webrtc.org/getting-started/overview/)) provides peer media with standard browser and Flutter (`flutter_webrtc`) clients.
- Laravel stores **session state and signaling**; media stays on the WebRTC data plane unless recording is explicitly added later.

### TURN / STUN

Most mobile networks require TURN for reliable connectivity:

- **STUN:** Public Google STUN or self-hosted for ICE discovery ([STUN](https://datatracker.ietf.org/doc/html/rfc8489)).
- **TURN:** Deploy [coturn](https://github.com/coturn/coturn) on a small VPS or use a managed TURN provider; credentials issued short-lived via API when a session starts.

Document in ops runbook:

- TURN hostname, ports (typically 3478 UDP/TCP, TLS 5349)
- Credential TTL aligned with session id
- Firewall rules separate from Laravel EC2

Signaling endpoints must require `device:session:signaling` or admin equivalent abilities.

## Why FCM for push alerts only

[Firebase Cloud Messaging](https://firebase.google.com/docs/cloud-messaging) delivers **wake notifications** (“pending command”) to reduce poll interval.

**FCM is not used for:**

- Granting location, camera, or microphone access
- Starting foreground capture without in-app consent UI
- Silent exfiltration of sensor data

Device must open app or execute a declared foreground service with notification after user policy acceptance.

## Admin UI: Blade vs SPA

**Decision:** Blade admin for faster iteration with Laravel auth, CSRF, and server-rendered forms; Vite for assets ([Blade](https://laravel.com/docs/12.x/blade)).

A separate React/Vue admin SPA can be added later if needed; API v1 remains the contract for mobile.

## Command queue vs direct socket

**Decision:** Command **queue with poll/FCM nudge** for reliability on Doze and flaky networks; WebSocket optional for admin dashboard freshness only.

## Documentation map

| Topic | File |
|-------|------|
| Architecture | `research/architecture/overview.md` |
| API | `research/laravel_api/api_design.md` |
| Android permissions | `research/android_permissions/permissions.md` |
| Flutter packages | `research/flutter_packages/packages.md` |
| EC2 testing | `research/aws_deployment/ec2_testing.md` |
| Security | `research/security/decisions.md` |

## Open items (post–Phase 2)

- Implement `/api/v1` routes and models
- Add Flutter dependencies and enrollment flow
- Configure FCM project and TURN server for staging
- MFA for admin via Fortify
