import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import '../core/constants.dart';

class AuthStorage {
  AuthStorage({FlutterSecureStorage? storage})
      : _storage = storage ?? const FlutterSecureStorage();

  final FlutterSecureStorage _storage;

  Future<String?> getDeviceToken() => _storage.read(key: StorageKeys.deviceToken);

  Future<void> saveEnrollment({
    required String deviceToken,
    required String deviceId,
    required String deviceName,
  }) async {
    await _storage.write(key: StorageKeys.deviceToken, value: deviceToken);
    await _storage.write(key: StorageKeys.deviceId, value: deviceId);
    await _storage.write(key: StorageKeys.deviceName, value: deviceName);
    await _storage.write(
      key: StorageKeys.enrolledAt,
      value: DateTime.now().toIso8601String(),
    );
  }

  Future<String?> getDeviceId() => _storage.read(key: StorageKeys.deviceId);

  Future<String?> getDeviceName() => _storage.read(key: StorageKeys.deviceName);

  Future<bool> isEnrolled() async {
    final token = await getDeviceToken();
    return token != null && token.isNotEmpty;
  }

  Future<void> clearEnrollment() async {
    await _storage.delete(key: StorageKeys.deviceToken);
    await _storage.delete(key: StorageKeys.deviceId);
    await _storage.delete(key: StorageKeys.deviceName);
    await _storage.delete(key: StorageKeys.enrolledAt);
  }

  Future<bool?> getLocationConsentLocal() async {
    final v = await _storage.read(key: StorageKeys.locationConsent);
    if (v == null) return null;
    return v == 'true';
  }

  Future<void> setLocationConsentLocal(bool value) =>
      _storage.write(key: StorageKeys.locationConsent, value: value.toString());
}
