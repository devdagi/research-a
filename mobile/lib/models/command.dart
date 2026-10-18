enum CommandType {
  checkIn,
  notify,
  requestLocation,
  requestStatus,
  unknown;

  static CommandType fromString(String? value) {
    switch (value?.toLowerCase()) {
      case 'check_in':
      case 'check-in':
        return CommandType.checkIn;
      case 'notify':
        return CommandType.notify;
      case 'request_location':
        return CommandType.requestLocation;
      case 'request_status':
      case 'status_report':
      case 'status':
        return CommandType.requestStatus;
      default:
        return CommandType.unknown;
    }
  }
}

class RemoteCommand {
  const RemoteCommand({
    required this.uuid,
    required this.type,
    required this.payload,
    required this.status,
    this.expiresAt,
    this.createdAt,
  });

  final String uuid;
  final CommandType type;
  final Map<String, dynamic> payload;
  final String status;
  final DateTime? expiresAt;
  final DateTime? createdAt;

  factory RemoteCommand.fromJson(Map<String, dynamic> json) {
    return RemoteCommand(
      uuid: json['uuid']?.toString() ?? json['id']?.toString() ?? '',
      type: CommandType.fromString(json['type'] as String?),
      payload: json['payload'] is Map<String, dynamic>
          ? json['payload'] as Map<String, dynamic>
          : {},
      status: json['status'] as String? ?? 'pending',
      expiresAt: json['expires_at'] != null
          ? DateTime.tryParse(json['expires_at'] as String)
          : null,
      createdAt: json['created_at'] != null
          ? DateTime.tryParse(json['created_at'] as String)
          : null,
    );
  }
}
