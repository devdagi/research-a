import 'package:company_device_manager/core/errors.dart';
import 'package:company_device_manager/models/location.dart';
import 'package:company_device_manager/services/auth_storage.dart';
import 'package:company_device_manager/services/location_service.dart';
import 'package:flutter_test/flutter_test.dart';

class _DenyConsentStorage extends AuthStorage {
  @override
  Future<bool?> getLocationConsentLocal() async => false;
}

void main() {
  test('uploadLocation blocked without consent', () async {
    final service = LocationService(authStorage: _DenyConsentStorage());

    expect(
      () => service.uploadLocation(
        const DeviceLocation(latitude: 1, longitude: 2),
      ),
      throwsA(isA<PermissionDeniedException>()),
    );
  });
}
