import '../core/constants.dart';
import '../models/consent.dart';
import 'api_client.dart';
import 'auth_storage.dart';

class ConsentService {
  ConsentService({
    ApiClient? apiClient,
    AuthStorage? authStorage,
  })  : _api = apiClient ?? ApiClient(),
        _authStorage = authStorage ?? AuthStorage();

  final ApiClient _api;
  final AuthStorage _authStorage;

  /// Company devices: features are allowed; keep local flag for location uploads.
  Future<ConsentSettings> loadMerged() async {
    await _authStorage.setLocationConsentLocal(true);
    return const ConsentSettings(
      locationSharing: true,
      notifications: true,
      liveSession: true,
    );
  }

  Future<void> setConsent({
    required String type,
    required String status,
  }) async {
    await _api.post(ApiPaths.consents, body: {
      'type': type,
      'status': status,
      'version': '1',
    });
    if (type == 'location') {
      await _authStorage.setLocationConsentLocal(status == 'granted');
    }
  }

  /// Ensure server has grants (for devices enrolled before auto-grant).
  Future<ConsentSettings> ensureServerGrants() async {
    try {
      for (final type in [
        'location',
        'live_camera',
        'live_microphone',
        'notifications',
      ]) {
        await setConsent(type: type, status: 'granted');
      }
    } catch (_) {}
    await _authStorage.setLocationConsentLocal(true);
    return const ConsentSettings(
      locationSharing: true,
      notifications: true,
      liveSession: true,
    );
  }
}
