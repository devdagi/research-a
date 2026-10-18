import 'dart:async';

import '../core/config.dart';
import '../core/constants.dart';
import 'api_client.dart';

class HeartbeatService {
  HeartbeatService({ApiClient? apiClient}) : _api = apiClient ?? ApiClient();

  final ApiClient _api;
  Timer? _timer;
  DateTime? lastHeartbeatAt;
  String? lastError;

  Future<void> sendHeartbeat() async {
    try {
      await _api.post(ApiPaths.heartbeat, body: {
        'timestamp': DateTime.now().toIso8601String(),
      });
      lastHeartbeatAt = DateTime.now();
      lastError = null;
    } catch (e) {
      lastError = e.toString();
      rethrow;
    }
  }

  void startPeriodic() {
    stopPeriodic();
    _timer = Timer.periodic(AppConfig.heartbeatInterval, (_) async {
      try {
        await sendHeartbeat();
      } catch (_) {
        // Surface via lastError; dashboard can show status.
      }
    });
  }

  void stopPeriodic() {
    _timer?.cancel();
    _timer = null;
  }

  void dispose() => stopPeriodic();
}
