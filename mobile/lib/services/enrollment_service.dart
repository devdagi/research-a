import 'dart:io';

import '../core/constants.dart';
import '../core/errors.dart';
import '../models/device.dart';
import '../models/enrollment.dart';
import 'api_client.dart';
import 'auth_storage.dart';

class EnrollmentService {
  EnrollmentService({
    ApiClient? apiClient,
    AuthStorage? authStorage,
  })  : _api = apiClient ?? ApiClient(),
        _authStorage = authStorage ?? AuthStorage();

  final ApiClient _api;
  final AuthStorage _authStorage;

  Future<DeviceInfo> collectDeviceInfo({String? deviceName}) async {
    final model = Platform.localHostname;
    return DeviceInfo(
      id: await _authStorage.getDeviceId(),
      name: deviceName?.trim().isNotEmpty == true
          ? deviceName!.trim()
          : (model.isNotEmpty ? model : 'Android Device'),
      platform: Platform.isAndroid ? 'android' : Platform.operatingSystem,
      model: model,
      osVersion: Platform.operatingSystemVersion,
    );
  }

  /// Registers this phone with the server — no company code.
  Future<EnrollmentResult> enrollOpen({String? deviceName, String? fcmToken}) async {
    final info = await collectDeviceInfo(deviceName: deviceName);

    final response = await _api.post(
      ApiPaths.enroll,
      authenticated: false,
      body: {
        ...info.toEnrollPayload(fcmToken: fcmToken),
      },
    );

    final result = EnrollmentResult.fromJson(response);
    if (result.deviceToken.isEmpty) {
      throw ApiException(
        message: 'Registration succeeded but no device token returned',
      );
    }

    await _authStorage.saveEnrollment(
      deviceToken: result.deviceToken,
      deviceId: result.deviceId,
      deviceName: info.name,
    );

    return result;
  }

  Future<void> unenroll() async {
    try {
      await _api.post(ApiPaths.unenroll);
    } catch (_) {
      // Still clear local credentials if the device was already revoked.
    }
    await _authStorage.clearEnrollment();
  }
}
