# Android Runtime Permissions — Company Device Manager

**Last verified:** 2026-10-15  
**Target:** Flutter 3.44.7 Android agent (compile/target SDK should follow current [Android API levels](https://developer.android.com/tools/releases/platforms))

This document maps OS permissions to CDM features. Official reference: [Request runtime permissions](https://developer.android.com/training/permissions/requesting) and [Location permissions overview](https://developer.android.com/develop/sensors-and-location/location/permissions).

## Design principle: no silent capture

Corporate policy does **not** override Android UX or Play policy:

- Every sensitive capability requires **runtime permission** and, where applicable, a **foreground service** with a visible notification.
- Camera, microphone, and screen capture must show the system indicator ([privacy indicators](https://developer.android.com/about/versions/12/behavior-changes-all#privacy-indicators)).
- Background location requires a separate approval step and strong justification ([access background location](https://developer.android.com/develop/sensors-and-location/location/permissions#request-background-location)).

## Permission matrix

| Permission / capability | Manifest declaration | Runtime prompt | CDM usage |
|-------------------------|----------------------|----------------|-----------|
| `ACCESS_FINE_LOCATION` | Yes | Yes | GPS/network fine location for check-in and geofence features |
| `ACCESS_COARSE_LOCATION` | Optional if fine granted | Yes | Fallback when fine denied |
| `ACCESS_BACKGROUND_LOCATION` | Yes (only if feature enabled) | Yes (dedicated flow after foreground) | Rare; policy-gated tracking when app not visible |
| `FOREGROUND_SERVICE` | Yes | N/A (service type declared) | Required base for ongoing location/session work |
| `FOREGROUND_SERVICE_LOCATION` | Yes (`android:foregroundServiceType="location"`) | User sees ongoing notification | Continuous location while duty/session active |
| `CAMERA` | Yes | Yes | Live session video, barcode enrollment helpers |
| `RECORD_AUDIO` | Yes | Yes | Live session audio |
| `POST_NOTIFICATIONS` | Yes (Android 13+) | Yes | Command alerts, session status, tracking disclosure |
| `INTERNET` | Yes | No | API and WebRTC |
| `ACCESS_NETWORK_STATE` | Yes | No | Connectivity gating (`connectivity_plus`) |

Foreground service location type is documented under [Foreground service types](https://developer.android.com/develop/background-work/services/fgs/types#location) and [Launch a foreground service](https://developer.android.com/develop/background-work/services/fgs/launch).

## Location — foreground vs background

### Foreground location

1. Request `ACCESS_FINE_LOCATION` (and coarse if needed) in context ([request only when needed](https://developer.android.com/training/permissions/requesting#request-permission)).
2. Start a **foreground service** with type `location` when tracking is active; notification text must state that location is being shared with the employer device manager.
3. Stop the service when tracking ends or the user revokes permission ([permission revocation](https://developer.android.com/training/permissions/requesting#handle-permission-denial)).

### Background location restrictions

From Android 10 (API 29) onward, background access is restricted ([Android 10 privacy changes](https://developer.android.com/about/versions/10/privacy/changes#app-data-access)). From Android 11+, background location is a **two-step** grant:

1. Foreground location approved in-app.
2. Separate `ACCESS_BACKGROUND_LOCATION` request, ideally after routing to Settings ([background location guidance](https://developer.android.com/develop/sensors-and-location/location/permissions#request-background-location)).

**CDM recommendation:** default to **foreground-only** location tied to an active shift/command; enable background only per-tenant policy with admin attestation and in-app disclosure.

## Camera and microphone

- Declare `CAMERA` and `RECORD_AUDIO` in the manifest; request at runtime before WebRTC or capture ([camera permissions](https://developer.android.com/training/camerax/architecture#permissions)).
- Use `permission_handler` or Flutter embedding APIs to detect `denied`, `deniedForever`, and `restricted` ([handle denial](https://developer.android.com/training/permissions/requesting#handle-permission-denial)).
- During live sessions, keep a persistent in-app banner (“Admin live session active”) in addition to the system indicator.

## Notifications (Android 13+)

`POST_NOTIFICATIONS` is runtime-gated ([notification runtime permission](https://developer.android.com/develop/ui/views/notifications/notification-permission)). Use notifications for:

- Pending command alerts (FCM-triggered poll),
- Foreground service disclosure,
- Live session state.

Do **not** use notifications to bypass consent for camera, mic, or background location.

## Manifest snippets (reference)

```xml
<!-- AndroidManifest.xml (illustrative — align with target SDK) -->
<uses-permission android:name="android.permission.INTERNET" />
<uses-permission android:name="android.permission.ACCESS_FINE_LOCATION" />
<uses-permission android:name="android.permission.ACCESS_COARSE_LOCATION" />
<uses-permission android:name="android.permission.ACCESS_BACKGROUND_LOCATION" />
<uses-permission android:name="android.permission.CAMERA" />
<uses-permission android:name="android.permission.RECORD_AUDIO" />
<uses-permission android:name="android.permission.FOREGROUND_SERVICE" />
<uses-permission android:name="android.permission.FOREGROUND_SERVICE_LOCATION" />
<uses-permission android:name="android.permission.POST_NOTIFICATIONS" />
```

Service declaration example:

```xml
<service
    android:name=".LocationTrackingService"
    android:foregroundServiceType="location"
    android:exported="false" />
```

See [Foreground service types](https://developer.android.com/develop/background-work/services/fgs/types) for required attributes per API level.

## Play policy and enterprise context

Even for company-owned devices, distribution via Google Play requires adherence to [User Data policies](https://support.google.com/googleplay/android-developer/answer/10144311) and [Device and Network Abuse](https://support.google.com/googleplay/android-developer/answer/9888379). MDM-only or private enterprise distribution may use [managed Google Play](https://developers.google.com/android/work) — document deployment mode in tenant settings.

## Flutter integration

- Use [`permission_handler`](https://pub.dev/packages/permission_handler) for unified permission status.
- Use [`geolocator`](https://pub.dev/packages/geolocator) for location streams with settings to disable background unless policy enables it.
- Map each command type to a **permission preflight** before execution; fail the command with an auditable `permission_denied` result rather than retrying silently.

## Testing checklist

- [ ] Fresh install: no permissions until feature used
- [ ] Deny once / deny forever flows reach settings deep link
- [ ] Revoke permission while FGS running — service stops, server notified
- [ ] Background location off by default; enabling shows extra disclosure
- [ ] Live session shows camera/mic indicators and in-app banner
- [ ] Android 13+ notification permission before FCM alert tests
