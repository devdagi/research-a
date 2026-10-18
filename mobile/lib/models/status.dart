class DeviceStatusPayload {
  const DeviceStatusPayload({
    required this.batteryLevel,
    required this.batteryState,
    required this.connectivity,
    this.extra,
  });

  final int batteryLevel;
  final String batteryState;
  final String connectivity;
  final Map<String, dynamic>? extra;

  Map<String, dynamic> toJson() => {
        'battery_level': batteryLevel,
        'battery_state': batteryState,
        'connectivity': connectivity,
        if (extra != null) ...extra!,
      };
}

class DeviceStatusSnapshot {
  const DeviceStatusSnapshot({
    required this.batteryLevel,
    required this.batteryState,
    required this.connectivity,
    required this.lastUpdated,
  });

  final int batteryLevel;
  final String batteryState;
  final String connectivity;
  final DateTime lastUpdated;
}
