class EnrollmentResult {
  const EnrollmentResult({
    required this.deviceToken,
    required this.deviceId,
    this.message,
  });

  final String deviceToken;
  final String deviceId;
  final String? message;

  factory EnrollmentResult.fromJson(Map<String, dynamic> json) {
    final data = json['data'] is Map<String, dynamic>
        ? json['data'] as Map<String, dynamic>
        : json;
    final device = data['device'] is Map<String, dynamic>
        ? data['device'] as Map<String, dynamic>
        : data;
    return EnrollmentResult(
      deviceToken: data['token'] as String? ??
          data['device_token'] as String? ??
          '',
      deviceId: device['uuid']?.toString() ??
          data['device_id']?.toString() ??
          data['id']?.toString() ??
          '',
      message: json['message'] as String?,
    );
  }
}
