import 'package:company_device_manager/services/auth_storage.dart';

class MemoryAuthStorage extends AuthStorage {
  MemoryAuthStorage(this.token);

  final String? token;

  @override
  Future<String?> getDeviceToken() async => token;
}
