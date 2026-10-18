# Company Device Manager (Android)

Flutter Android client for company device enrollment, heartbeat, status reporting, consent-aware location sharing, remote commands, and employee-controlled live sessions.

## Requirements

- Flutter 3.44.x / Dart 3.12.x
- Android SDK (API 21+)
- Laravel backend exposing `/api/v1/device/*` endpoints

## Configure API base URL

Never hardcode production URLs in source. Pass the backend URL at build/run time:

```bash
cd mobile
flutter pub get
flutter run \
  --dart-define=API_BASE_URL=https://your-company.example.com
```

Optional WebRTC TURN (recommended for live sessions behind NAT):

```bash
flutter run \
  --dart-define=API_BASE_URL=https://your-company.example.com \
  --dart-define=TURN_URL=turn:turn.example.com:3478 \
  --dart-define=TURN_USERNAME=your-user \
  --dart-define=TURN_CREDENTIAL=your-credential
```

Default when `API_BASE_URL` is omitted: `http://10.0.2.2:8000` (Android emulator → host Laravel `php artisan serve`). Paths are absolute under `/api/v1/device/*`.

## Quality checks

```bash
flutter analyze
flutter test
```

## Release build

```bash
flutter build apk \
  --dart-define=API_BASE_URL=https://your-company.example.com
```

## Permissions

The app explains each Android permission in the **Permissions** screen before requesting:

- Location — manual check-ins when consent is enabled
- Notifications — remote check-ins and live session requests
- Camera / microphone — only during an employee-approved live session
- Internet — API communication

## Unenrollment

Use **Privacy & data retention → Unenroll device** to remove the local device token and stop background polling.
