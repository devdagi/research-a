class DeviceInfo {
  const DeviceInfo({
    required this.id,
    required this.name,
    required this.platform,
    required this.model,
    required this.osVersion,
  });

  final String? id;
  final String name;
  final String platform;
  final String model;
  final String osVersion;

  factory DeviceInfo.fromJson(Map<String, dynamic> json) {
    return DeviceInfo(
      id: json['id']?.toString(),
      name: json['device_name'] as String? ?? json['name'] as String? ?? '',
      platform: json['platform'] as String? ?? 'android',
      model: json['model'] as String? ?? '',
      osVersion: json['os_version'] as String? ?? '',
    );
  }

  Map<String, dynamic> toEnrollPayload({String? fcmToken}) => {
        'device_name': name,
        'platform': platform,
        'model': model,
        'os_version': osVersion,
        if (fcmToken != null && fcmToken.isNotEmpty) 'fcm_token': fcmToken,
      };
}
