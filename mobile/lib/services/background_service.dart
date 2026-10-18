import 'dart:convert';
import 'dart:io';
import 'dart:ui';

import 'package:flutter/widgets.dart';
import 'package:flutter_foreground_task/flutter_foreground_task.dart';
import 'package:geolocator/geolocator.dart';

import '../core/config.dart';
import '../core/constants.dart';
import 'api_client.dart';
import 'auth_storage.dart';
import 'live_media_runtime.dart';
import 'live_session_service.dart';

/// Keeps the Android process alive and runs location + live-audio work from a
/// background isolate so admin still gets GPS / mic after Recents / leaving the app.
class BackgroundService {
  BackgroundService._();

  static bool _inited = false;
  static bool _running = false;
  static bool _wantMic = false;

  static bool get isRunning => _running;

  static Future<void> init() async {
    if (_inited || !Platform.isAndroid) return;
    FlutterForegroundTask.init(
      androidNotificationOptions: AndroidNotificationOptions(
        channelId: 'cdm_background',
        channelName: 'Company Device Manager',
        channelDescription:
            'Keeps location sharing and live audio available while locked.',
        // Higher importance so OEMs are less likely to freeze work on lock screen.
        channelImportance: NotificationChannelImportance.DEFAULT,
        priority: NotificationPriority.DEFAULT,
        onlyAlertOnce: true,
      ),
      iosNotificationOptions: const IOSNotificationOptions(
        showNotification: false,
        playSound: false,
      ),
      foregroundTaskOptions: ForegroundTaskOptions(
        // 1s ticks so background location matches foreground rate.
        eventAction: ForegroundTaskEventAction.repeat(1000),
        // Restart location + live-audio TaskHandler after power off / reboot.
        autoRunOnBoot: true,
        autoRunOnMyPackageReplaced: true,
        allowWakeLock: true,
        allowWifiLock: true,
        allowAutoRestart: true,
        stopWithTask: false,
      ),
    );
    _inited = true;
  }

  /// After reboot (or cold start): if device is enrolled, bring FGS back
  /// so map GPS + live listen work without waiting for the user to open the UI.
  static Future<void> restoreIfEnrolled() async {
    if (!Platform.isAndroid) return;
    try {
      final storage = AuthStorage();
      if (!await storage.isEnrolled()) return;
      final token = await storage.getDeviceToken();
      if (token == null || token.isEmpty) return;
      await sync(
        enrolled: true,
        locationTracking: true,
        liveListenEnabled: true,
        deviceToken: token,
      );
      await requestForceReconnect();
    } catch (_) {}
  }

  static Future<void> ensureNotificationPermission() async {
    if (!Platform.isAndroid) return;
    try {
      final perm = await FlutterForegroundTask.checkNotificationPermission();
      if (perm != NotificationPermission.granted) {
        await FlutterForegroundTask.requestNotificationPermission();
      }
    } catch (_) {}
  }

  static Future<void> _persistWorkFlags({
    required String? deviceToken,
    required bool locationTracking,
    required bool liveListenEnabled,
  }) async {
    await FlutterForegroundTask.saveData(
      key: 'location_enabled',
      value: locationTracking,
    );
    await FlutterForegroundTask.saveData(
      key: 'live_listen_enabled',
      value: liveListenEnabled,
    );
    await FlutterForegroundTask.saveData(
      key: 'api_base',
      value: AppConfig.apiBaseUrl,
    );
    if (deviceToken != null && deviceToken.isNotEmpty) {
      await FlutterForegroundTask.saveData(
        key: 'device_token',
        value: deviceToken,
      );
    }
  }

  static Future<void> start({
    required String reason,
    required bool location,
    required bool microphone,
  }) async {
    if (!Platform.isAndroid) return;
    await init();
    await ensureNotificationPermission();

    try {
      if (!await FlutterForegroundTask.isIgnoringBatteryOptimizations) {
        await FlutterForegroundTask.requestIgnoreBatteryOptimization();
      }
    } catch (_) {}

    final types = <ForegroundServiceTypes>[
      ForegroundServiceTypes.dataSync,
      // Always declare location so Android keeps the process eligible for GPS.
      ForegroundServiceTypes.location,
      if (microphone) ForegroundServiceTypes.microphone,
    ];

    final needRestart = _running &&
        (await FlutterForegroundTask.isRunningService) &&
        (_wantMic != microphone);

    try {
      if (needRestart) {
        await stop();
      }

      if (await FlutterForegroundTask.isRunningService) {
        await FlutterForegroundTask.updateService(
          notificationTitle: 'Company Device Manager',
          notificationText: reason,
          callback: _foregroundCallback,
        );
        _running = true;
        _wantMic = microphone;
        return;
      }

      final result = await FlutterForegroundTask.startService(
        serviceTypes: types,
        notificationTitle: 'Company Device Manager',
        notificationText: reason,
        callback: _foregroundCallback,
      );
      _running = result is ServiceRequestSuccess;
      _wantMic = microphone;
    } catch (_) {
      _running = false;
    }
  }

  static Future<void> stop() async {
    if (!Platform.isAndroid) return;
    try {
      if (await FlutterForegroundTask.isRunningService) {
        await FlutterForegroundTask.stopService();
      }
    } catch (_) {}
    _running = false;
    _wantMic = false;
  }

  /// Ask the FGS isolate to end the current live WebRTC session.
  static Future<void> requestEndLiveSession() async {
    if (!Platform.isAndroid) return;
    await FlutterForegroundTask.saveData(key: 'live_end_requested', value: true);
  }

  /// After unlock / resume — force TaskHandler to rebuild mic WebRTC offer.
  static Future<void> requestForceReconnect() async {
    if (!Platform.isAndroid) return;
    await FlutterForegroundTask.saveData(key: 'live_force_reconnect', value: true);
  }

  /// Keep FGS up whenever enrolled so Recents cannot kill location / audio.
  /// Microphone type stays on while enrolled so admin can listen without
  /// reopening the app.
  static Future<void> sync({
    required bool enrolled,
    required bool locationTracking,
    required bool liveListenEnabled,
    String? deviceToken,
  }) async {
    if (!Platform.isAndroid) return;
    if (!enrolled) {
      await stop();
      return;
    }

    await _persistWorkFlags(
      deviceToken: deviceToken,
      locationTracking: locationTracking,
      liveListenEnabled: liveListenEnabled,
    );

    final parts = <String>['running'];
    if (locationTracking) parts.add('location');
    if (liveListenEnabled) parts.add('mic ready');

    await start(
      reason: parts.join(' · '),
      location: locationTracking,
      microphone: liveListenEnabled,
    );
  }

  static Future<String?> liveStatusMessage() async {
    if (!Platform.isAndroid) return null;
    return FlutterForegroundTask.getData<String>(key: 'live_status');
  }

  static Future<String?> liveActiveUuid() async {
    if (!Platform.isAndroid) return null;
    return FlutterForegroundTask.getData<String>(key: 'live_active_uuid');
  }
}

@pragma('vm:entry-point')
void _foregroundCallback() {
  WidgetsFlutterBinding.ensureInitialized();
  DartPluginRegistrant.ensureInitialized();
  FlutterForegroundTask.setTaskHandler(_CdmTaskHandler());
}

class _CdmTaskHandler extends TaskHandler {
  bool _locBusy = false;
  bool _liveBusy = false;
  int _tick = 0;
  LiveMediaRuntime? _live;
  LiveSessionService? _sessions;

  Future<LiveMediaRuntime> _ensureLive() async {
    if (_live != null) return _live!;
    final token =
        await FlutterForegroundTask.getData<String>(key: 'device_token');
    final base =
        await FlutterForegroundTask.getData<String>(key: 'api_base') ??
            AppConfig.apiBaseUrl;
    _sessions = LiveSessionService(
      apiClient: ApiClient(baseUrl: base, fixedToken: token),
    );
    _live = LiveMediaRuntime(liveSessionService: _sessions);
    _live!.onChanged = () {
      _persistLiveStatus();
    };
    return _live!;
  }

  @override
  Future<void> onStart(DateTime timestamp, TaskStarter starter) async {
    // After reboot, re-enable map + mic work if we still have a device token.
    try {
      final token =
          await FlutterForegroundTask.getData<String>(key: 'device_token');
      if (token != null && token.isNotEmpty) {
        final loc =
            await FlutterForegroundTask.getData<bool>(key: 'location_enabled');
        if (loc != true) {
          await FlutterForegroundTask.saveData(
            key: 'location_enabled',
            value: true,
          );
        }
        final live =
            await FlutterForegroundTask.getData<bool>(key: 'live_listen_enabled');
        if (live != true) {
          await FlutterForegroundTask.saveData(
            key: 'live_listen_enabled',
            value: true,
          );
        }
        await FlutterForegroundTask.updateService(
          notificationTitle: 'Company Device Manager',
          notificationText: 'running · location · mic ready (after boot)',
        );
      }
    } catch (_) {}
    await _ensureLive();
  }

  @override
  void onRepeatEvent(DateTime timestamp) {
    _uploadLocationTick();
    _tick++;
    // Live-session poll ~every 3s (admin listen without opening the app).
    if (_tick % 3 == 0) {
      _pollLiveAudio();
    }
  }

  Future<void> _persistLiveStatus() async {
    try {
      final live = _live;
      if (live == null) return;
      final uuid = live.activeSession?.uuid;
      if (uuid != null) {
        await FlutterForegroundTask.saveData(key: 'live_active_uuid', value: uuid);
      } else {
        await FlutterForegroundTask.removeData(key: 'live_active_uuid');
      }
      final msg = live.statusMessage;
      if (msg != null) {
        await FlutterForegroundTask.saveData(key: 'live_status', value: msg);
      } else {
        await FlutterForegroundTask.removeData(key: 'live_status');
      }
      if (live.isActive) {
        await FlutterForegroundTask.updateService(
          notificationTitle: 'Company Device Manager',
          notificationText: 'Live audio streaming to admin',
        );
      }
    } catch (_) {}
  }

  Future<void> _pollLiveAudio() async {
    if (_liveBusy) return;
    _liveBusy = true;
    try {
      final live = await _ensureLive();
      final endRequested =
          await FlutterForegroundTask.getData<bool>(key: 'live_end_requested') ??
              false;
      if (endRequested) {
        await FlutterForegroundTask.saveData(key: 'live_end_requested', value: false);
        await live.disconnect(notifyServer: true);
        await _persistLiveStatus();
        return;
      }

      final forceReconnect =
          await FlutterForegroundTask.getData<bool>(key: 'live_force_reconnect') ??
              false;
      if (forceReconnect) {
        await FlutterForegroundTask.saveData(key: 'live_force_reconnect', value: false);
      }

      final listen =
          await FlutterForegroundTask.getData<bool>(key: 'live_listen_enabled') ??
              false;
      if (!listen) {
        if (live.isActive) {
          await live.disconnect(notifyServer: true);
          await _persistLiveStatus();
        }
        return;
      }

      final api = _sessions ?? LiveSessionService();
      final actionable = await api.fetchPendingSessions();
      final next = actionable.isNotEmpty ? actionable.first : null;

      if (next == null) {
        if (live.isActive) {
          await live.disconnect(notifyServer: false);
          await _persistLiveStatus();
        }
        return;
      }

      final activeUuid = live.activeSession?.uuid;
      final healthy = live.mediaHealthy;
      if (activeUuid == next.uuid && healthy && !forceReconnect) {
        await _persistLiveStatus();
        return;
      }

      // Same session but dead after lock/reboot — rebuild offer.
      if (activeUuid == next.uuid && (!healthy || forceReconnect)) {
        await live.connect(next, force: true);
        await _persistLiveStatus();
        return;
      }

      if (activeUuid != null && activeUuid != next.uuid) {
        await live.disconnect(notifyServer: true);
      }

      await live.connect(next, force: forceReconnect);
      await _persistLiveStatus();
    } catch (e) {
      try {
        await FlutterForegroundTask.saveData(
          key: 'live_status',
          value: e.toString(),
        );
      } catch (_) {}
    } finally {
      _liveBusy = false;
    }
  }

  Future<void> _uploadLocationTick() async {
    if (_locBusy) return;
    _locBusy = true;
    HttpClient? client;
    try {
      // Default true after boot when token exists (see onStart).
      final enabled =
          await FlutterForegroundTask.getData<bool>(key: 'location_enabled') ??
              true;
      if (!enabled) return;

      final token =
          await FlutterForegroundTask.getData<String>(key: 'device_token');
      final base =
          await FlutterForegroundTask.getData<String>(key: 'api_base');
      if (token == null || token.isEmpty || base == null || base.isEmpty) {
        return;
      }

      final permission = await Geolocator.checkPermission();
      if (permission == LocationPermission.denied ||
          permission == LocationPermission.deniedForever) {
        return;
      }

      // Lock screen often makes a fresh GPS fix time out — fall back to last known.
      Position? position;
      try {
        position = await Geolocator.getCurrentPosition(
          locationSettings: AndroidSettings(
            accuracy: LocationAccuracy.high,
            distanceFilter: 0,
            forceLocationManager: true,
            intervalDuration: const Duration(seconds: 1),
            timeLimit: const Duration(seconds: 6),
          ),
        );
      } catch (_) {
        position = await Geolocator.getLastKnownPosition();
      }
      if (position == null) return;

      final body = jsonEncode({
        'latitude': position.latitude,
        'longitude': position.longitude,
        'accuracy': position.accuracy,
        'recorded_at': DateTime.now().toUtc().toIso8601String(),
      });

      final path =
          ApiPaths.location.startsWith('/') ? ApiPaths.location : '/${ApiPaths.location}';
      final uri = Uri.parse('$base$path');

      client = HttpClient()
        ..connectionTimeout = const Duration(seconds: 10)
        ..findProxy = (_) => 'DIRECT';
      final req = await client.postUrl(uri);
      req.headers.set(HttpHeaders.acceptHeader, 'application/json');
      req.headers.set(HttpHeaders.contentTypeHeader, 'application/json');
      req.headers.set(HttpHeaders.authorizationHeader, 'Bearer $token');
      req.add(utf8.encode(body));
      final res = await req.close();
      await res.drain<void>();
    } catch (_) {
      // Keep service alive even if one upload fails.
    } finally {
      client?.close(force: true);
      _locBusy = false;
    }
  }

  @override
  Future<void> onDestroy(DateTime timestamp, bool isTimeout) async {
    try {
      await _live?.disconnect(notifyServer: false);
    } catch (_) {}
  }
}
