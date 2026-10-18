import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:permission_handler/permission_handler.dart';

import '../../models/consent.dart';
import '../../models/live_session.dart';
import '../../models/location.dart';
import '../../models/status.dart';
import '../../services/auth_storage.dart';
import '../../services/background_service.dart';
import '../../services/command_service.dart';
import '../../services/consent_service.dart';
import '../../services/enrollment_service.dart';
import '../../services/heartbeat_service.dart';
import '../../services/live_media_runtime.dart';
import '../../services/live_session_service.dart';
import '../../services/location_service.dart';
import '../../services/notification_service.dart';
import '../../services/status_service.dart';

enum AppBootstrapStatus { loading, welcome, dashboard }

class AppState extends ChangeNotifier {
  AppState({
    AuthStorage? authStorage,
    EnrollmentService? enrollmentService,
    HeartbeatService? heartbeatService,
    StatusService? statusService,
    CommandService? commandService,
    ConsentService? consentService,
    LiveSessionService? liveSessionService,
    NotificationService? notificationService,
    LocationService? locationService,
    LiveMediaRuntime? liveMediaRuntime,
  })  : _authStorage = authStorage ?? AuthStorage(),
        enrollmentService = enrollmentService ?? EnrollmentService(),
        heartbeatService = heartbeatService ?? HeartbeatService(),
        statusService = statusService ?? StatusService(),
        commandService = commandService ?? CommandService(),
        consentService = consentService ?? ConsentService(),
        liveSessionService = liveSessionService ?? LiveSessionService(),
        notificationService = notificationService ?? NotificationService(),
        locationService = locationService ?? LocationService(),
        liveMedia = liveMediaRuntime ?? LiveMediaRuntime() {
    liveMedia.onChanged = () {
      notifyListeners();
      _syncBackgroundService();
    };
  }

  final AuthStorage _authStorage;
  Timer? _liveSessionPoll;

  final EnrollmentService enrollmentService;
  final HeartbeatService heartbeatService;
  final StatusService statusService;
  final CommandService commandService;
  final ConsentService consentService;
  final LiveSessionService liveSessionService;
  final NotificationService notificationService;
  final LocationService locationService;
  final LiveMediaRuntime liveMedia;

  AppBootstrapStatus status = AppBootstrapStatus.loading;
  ConsentSettings? consent;
  DeviceStatusSnapshot? deviceStatus;
  LiveSession? pendingLiveSession;
  LiveSession? _bgLiveSession;
  DeviceLocation? lastLiveLocation;
  bool locationLiveTracking = false;
  String? deviceName;
  String? lastError;
  String? backgroundLiveStatus;
  bool _bootstrapping = false;

  /// Live audio is owned by the FGS TaskHandler so it works with the app closed.
  LiveSession? get activeLiveSession =>
      liveMedia.activeSession ?? _bgLiveSession;

  Future<void> bootstrap() async {
    if (_bootstrapping) return;
    _bootstrapping = true;
    status = AppBootstrapStatus.loading;
    notifyListeners();

    final enrolled = await _authStorage.isEnrolled();
    deviceName = await _authStorage.getDeviceName();

    try {
      if (!enrolled) {
        try {
          await enrollmentService.enrollOpen();
          deviceName = await _authStorage.getDeviceName();
        } catch (e) {
          lastError = e.toString();
          status = AppBootstrapStatus.welcome;
          notifyListeners();
          return;
        }
      }

      consent = await consentService.ensureServerGrants();
      // OS location + notification dialogs — required for background GPS / FGS.
      try {
        await locationService.ensurePermission(requestIfNeeded: true);
      } catch (_) {}
      // Mic must be granted once in the UI; FGS cannot show the permission dialog.
      try {
        await Permission.microphone.request();
      } catch (_) {}
      await BackgroundService.ensureNotificationPermission();
      await refreshStatus();
      heartbeatService.startPeriodic();
      commandService.startPolling();
      await _refreshLiveSessions();
      _startLiveSessionPolling();
      try {
        await startLocationLiveTracking();
      } catch (_) {
        // Still mark tracking so FGS TaskHandler keeps trying GPS uploads.
        locationLiveTracking = true;
      }
      await _syncBackgroundService();

      status = AppBootstrapStatus.dashboard;
      notifyListeners();
    } finally {
      _bootstrapping = false;
    }
  }

  Future<void> retryAutoEnroll() => bootstrap();

  Future<void> _syncBackgroundService() async {
    final enrolled =
        status == AppBootstrapStatus.dashboard || await _authStorage.isEnrolled();
    final token = await _authStorage.getDeviceToken();
    // Keep microphone FGS type on whenever enrolled so admin can listen
    // without the user reopening the app.
    final liveListen = enrolled && (consent?.liveSession != false);
    await BackgroundService.sync(
      enrolled: enrolled,
      locationTracking: locationLiveTracking,
      liveListenEnabled: liveListen,
      deviceToken: token,
    );
  }

  /// Call when app returns to foreground / unlock — restore FGS + re-offer mic.
  Future<void> onAppResumed() async {
    if (status != AppBootstrapStatus.dashboard) return;
    await BackgroundService.requestForceReconnect();
    await _syncBackgroundService();
    if (locationLiveTracking && !locationService.isStreaming) {
      try {
        await startLocationLiveTracking();
      } catch (_) {}
    }
    await _refreshLiveSessions();
  }

  /// Persist FGS flags when screen turns off — do not tear anything down.
  Future<void> onAppPaused() async {
    if (status != AppBootstrapStatus.dashboard) return;
    await _syncBackgroundService();
  }

  void _startLiveSessionPolling() {
    _liveSessionPoll?.cancel();
    _liveSessionPoll = Timer.periodic(const Duration(seconds: 3), (_) {
      _refreshLiveSessions();
    });
  }

  Future<void> refreshStatus() async {
    try {
      deviceStatus = await statusService.collectLocalStatus();
      lastError = null;
    } catch (e) {
      lastError = e.toString();
    }
    notifyListeners();
  }

  Future<void> _refreshLiveSessions() async {
    try {
      // FGS TaskHandler owns WebRTC; mirror its status into the UI.
      backgroundLiveStatus = await BackgroundService.liveStatusMessage();
      final bgUuid = await BackgroundService.liveActiveUuid();
      final actionable = await liveSessionService.fetchPendingSessions();
      final next = actionable.isNotEmpty ? actionable.first : null;

      if (bgUuid != null && bgUuid.isNotEmpty) {
        LiveSession? matched;
        for (final s in actionable) {
          if (s.uuid == bgUuid) {
            matched = s;
            break;
          }
        }
        _bgLiveSession = matched ??
            (_bgLiveSession?.uuid == bgUuid ? _bgLiveSession : null) ??
            (next?.uuid == bgUuid ? next : null);
        pendingLiveSession = null;
        if (backgroundLiveStatus != null) {
          liveMedia.statusMessage = backgroundLiveStatus;
        }
      } else {
        _bgLiveSession = null;
        // Auto-connect runs in FGS; keep row visible until bg uuid appears.
        pendingLiveSession = next;
      }
      lastError = null;
    } catch (e) {
      lastError = e.toString();
      pendingLiveSession = null;
    }
    notifyListeners();
  }

  Future<void> pollLiveSessions() => _refreshLiveSessions();

  void setLastLiveLocation(DeviceLocation loc) {
    lastLiveLocation = loc;
    notifyListeners();
  }

  Future<void> startLocationLiveTracking() async {
    await locationService.startLiveTracking(
      onUpdate: (loc) {
        lastLiveLocation = loc;
        notifyListeners();
      },
      onError: (e) {
        lastError = e.toString();
        notifyListeners();
      },
    );
    locationLiveTracking = true;
    await _syncBackgroundService();
    notifyListeners();
  }

  Future<void> stopLocationLiveTracking() async {
    await locationService.stopLiveTracking();
    locationLiveTracking = false;
    await _syncBackgroundService();
    notifyListeners();
  }

  Future<void> endActiveLiveSession() async {
    await BackgroundService.requestEndLiveSession();
    await liveMedia.disconnect(notifyServer: true);
    _bgLiveSession = null;
    backgroundLiveStatus = null;
    await _syncBackgroundService();
    notifyListeners();
  }

  Future<void> unenroll() async {
    heartbeatService.stopPeriodic();
    commandService.stopPolling();
    _liveSessionPoll?.cancel();
    _liveSessionPoll = null;
    await stopLocationLiveTracking();
    await liveMedia.disconnect(notifyServer: false);
    await BackgroundService.stop();
    await enrollmentService.unenroll();
    consent = null;
    deviceStatus = null;
    pendingLiveSession = null;
    lastLiveLocation = null;
    status = AppBootstrapStatus.welcome;
    notifyListeners();
  }

  @override
  void dispose() {
    // Intentionally do NOT stop timers, WebRTC, or the foreground service.
    // Recents / Activity teardown must leave location + live audio running.
    // Cleanup happens only in [unenroll].
    super.dispose();
  }
}
