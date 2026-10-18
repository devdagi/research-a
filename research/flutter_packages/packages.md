# Flutter Packages — Company Device Manager

**Last verified:** 2026-10-15  
**SDK constraint (project):** Dart `^3.12.2` in `mobile/pubspec.yaml`  
**Flutter:** 3.44.7 (see `mobile/.dart_tool/version`)

Pin exact versions at implementation time with `flutter pub add <package>` so Pub resolves against your current Flutter/Dart toolchain. Verify compatibility on [pub.dev](https://pub.dev/) and in `pubspec.lock`.

## Recommended dependencies

| Area | Package | Pub.dev | Notes |
|------|---------|---------|-------|
| HTTP client | `dio` or `http` | [dio](https://pub.dev/packages/dio) / [http](https://pub.dev/packages/http) | `dio` for interceptors (auth header, retry, logging); `http` for minimal surface |
| Secure storage | `flutter_secure_storage` | [flutter_secure_storage](https://pub.dev/packages/flutter_secure_storage) | Store Sanctum device token and device UUID (Android Keystore) |
| Location | `geolocator` | [geolocator](https://pub.dev/packages/geolocator) | Foreground-first; disable background unless policy requires |
| Battery | `battery_plus` | [battery_plus](https://pub.dev/packages/battery_plus) | Telemetry for fleet health |
| Connectivity | `connectivity_plus` | [connectivity_plus](https://pub.dev/packages/connectivity_plus) | Backoff polling when offline |
| Push alerts | `firebase_messaging` | [firebase_messaging](https://pub.dev/packages/firebase_messaging) | **Alerts only** — prompt user to open app; not consent for sensors |
| WebRTC | `flutter_webrtc` | [flutter_webrtc](https://pub.dev/packages/flutter_webrtc) | Live session media; requires platform permissions |
| Permissions | `permission_handler` | [permission_handler](https://pub.dev/packages/permission_handler) | Maps to Android runtime permissions |
| State | `riverpod` or `provider` | [flutter_riverpod](https://pub.dev/packages/flutter_riverpod) / [provider](https://pub.dev/packages/provider) | Riverpod recommended for testability; Provider is simpler for small apps |

## Install commands (verify versions locally)

From `mobile/`:

```bash
flutter pub add dio flutter_secure_storage geolocator battery_plus connectivity_plus
flutter pub add firebase_messaging flutter_webrtc permission_handler flutter_riverpod
# or: flutter pub add provider
```

Then run:

```bash
flutter pub get
dart analyze
flutter test
```

Document resolved versions from `pubspec.lock` in release notes when cutting builds.

## Firebase / FCM setup

Follow [FlutterFire overview](https://firebase.google.com/docs/flutter/setup):

1. Create Firebase project and Android app with application ID matching `mobile/android/app/build.gradle.kts`.
2. Add `google-services.json` (do **not** commit secrets — see root `.gitignore`).
3. Configure FCM for high-priority data messages that trigger **command poll**, not automatic capture.

Reference: [Firebase Cloud Messaging](https://firebase.google.com/docs/cloud-messaging).

## Build-time API base URL

Avoid hardcoding environment URLs. Use `--dart-define`:

```dart
// lib/config/env.dart
class Env {
  static const apiBaseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'http://10.0.2.2:8000',
  );
}
```

Build/run examples:

```bash
flutter run --dart-define=API_BASE_URL=http://10.0.2.2:8000
flutter build apk --dart-define=API_BASE_URL=https://testing.example.com
```

Official: [Flutter build modes and dart-define](https://docs.flutter.dev/deployment/flavors#dart-defines).

## WebRTC notes

- `flutter_webrtc` depends on native WebRTC builds; align NDK/Gradle with [package README](https://pub.dev/packages/flutter_webrtc).
- Request camera/mic via `permission_handler` before `getUserMedia`.
- ICE/TURN servers are configured from API signaling responses (see architecture overview).

## Platform minimums

- **Java 17** (Temurin) for Android Gradle Plugin — matches Android Studio recommendations ([Java versions](https://developer.android.com/build/jdk)).
- Enable multidex if method count grows ([multidex](https://developer.android.com/build/multidex)).

## Packages intentionally omitted (initial phase)

| Package | Reason |
|---------|--------|
| Background geolocation plugins | Conflicts with consent-first design unless strictly policy-controlled |
| Silent screenshot libraries | Violates “no silent capture” requirement |
| Root/MDM bypass kits | Out of scope for standard Play/enterprise agent |

## Related documents

- `research/android_permissions/permissions.md`
- `research/laravel_api/api_design.md`
- `research/architecture/overview.md`
