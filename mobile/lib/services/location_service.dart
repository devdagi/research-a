import 'dart:async';

import 'package:geolocator/geolocator.dart' hide PermissionDeniedException;

import '../core/constants.dart';
import '../core/errors.dart';
import '../models/location.dart';
import 'api_client.dart';
import 'auth_storage.dart';

class LocationService {
  LocationService({
    ApiClient? apiClient,
    AuthStorage? authStorage,
  })  : _api = apiClient ?? ApiClient(),
        _authStorage = authStorage ?? AuthStorage();

  final ApiClient _api;
  final AuthStorage _authStorage;

  Timer? _timer;
  bool _uploading = false;
  DeviceLocation? lastUploaded;

  bool get isStreaming => _timer != null;

  Future<bool> ensurePermission({required bool requestIfNeeded}) async {
    final serviceEnabled = await Geolocator.isLocationServiceEnabled();
    if (!serviceEnabled) {
      throw PermissionDeniedException('location services disabled');
    }

    var permission = await Geolocator.checkPermission();
    if (permission == LocationPermission.denied && requestIfNeeded) {
      permission = await Geolocator.requestPermission();
    }

    if (permission == LocationPermission.denied) {
      throw PermissionDeniedException('location');
    }
    if (permission == LocationPermission.deniedForever) {
      throw PermissionDeniedException('location (permanently denied)');
    }
    return true;
  }

  Future<DeviceLocation> getCurrentLocation({bool requestIfNeeded = false}) async {
    await ensurePermission(requestIfNeeded: requestIfNeeded);
    final position = await Geolocator.getCurrentPosition(
      locationSettings: const LocationSettings(
        accuracy: LocationAccuracy.high,
      ),
    );
    return DeviceLocation(
      latitude: position.latitude,
      longitude: position.longitude,
      accuracy: position.accuracy,
      recordedAt: DateTime.now(),
    );
  }

  Future<void> uploadLocation(DeviceLocation location) async {
    final consent = await _authStorage.getLocationConsentLocal();
    if (consent != true) {
      throw PermissionDeniedException('location sharing consent');
    }
    await _api.post(ApiPaths.location, body: location.toJson());
    lastUploaded = location;
  }

  /// Uploads GPS to the API about once per second while consent is granted.
  Future<void> startLiveTracking({
    void Function(DeviceLocation loc)? onUpdate,
    void Function(Object error)? onError,
  }) async {
    await stopLiveTracking();
    await ensurePermission(requestIfNeeded: true);

    final consent = await _authStorage.getLocationConsentLocal();
    if (consent != true) {
      throw PermissionDeniedException('location sharing consent');
    }

    Future<void> tick() async {
      if (_uploading) return;
      _uploading = true;
      try {
        final loc = await getCurrentLocation();
        onUpdate?.call(loc);
        await uploadLocation(loc);
      } catch (e) {
        onError?.call(e);
      } finally {
        _uploading = false;
      }
    }

    await tick();
    _timer = Timer.periodic(const Duration(seconds: 1), (_) => tick());
  }

  Future<void> stopLiveTracking() async {
    _timer?.cancel();
    _timer = null;
  }

  void dispose() {
    stopLiveTracking();
  }
}
