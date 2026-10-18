import 'package:battery_plus/battery_plus.dart';
import 'package:connectivity_plus/connectivity_plus.dart';

import '../core/constants.dart';
import '../models/status.dart';
import 'api_client.dart';

class StatusService {
  StatusService({
    ApiClient? apiClient,
    Battery? battery,
    Connectivity? connectivity,
  })  : _api = apiClient ?? ApiClient(),
        _battery = battery ?? Battery(),
        _connectivity = connectivity ?? Connectivity();

  final ApiClient _api;
  final Battery _battery;
  final Connectivity _connectivity;

  Future<DeviceStatusSnapshot> collectLocalStatus() async {
    final level = await _battery.batteryLevel;
    final state = await _battery.batteryState;
    final results = await _connectivity.checkConnectivity();
    final connectivityLabel = results.isEmpty
        ? 'none'
        : results.map((e) => e.name).join(', ');

    return DeviceStatusSnapshot(
      batteryLevel: level,
      batteryState: state.name,
      connectivity: connectivityLabel,
      lastUpdated: DateTime.now(),
    );
  }

  Future<void> uploadStatus() async {
    final snapshot = await collectLocalStatus();
    final payload = DeviceStatusPayload(
      batteryLevel: snapshot.batteryLevel,
      batteryState: snapshot.batteryState,
      connectivity: snapshot.connectivity,
    );
    await _api.post(ApiPaths.status, body: payload.toJson());
  }
}
