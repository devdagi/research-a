enum LiveSessionStatus {
  pending,
  approved,
  denied,
  active,
  ended,
  expired,
  cancelled,
  unknown;

  static LiveSessionStatus fromString(String? value) {
    switch (value?.toLowerCase()) {
      case 'pending':
        return LiveSessionStatus.pending;
      case 'approved':
        return LiveSessionStatus.approved;
      case 'denied':
      case 'rejected':
        return LiveSessionStatus.denied;
      case 'active':
        return LiveSessionStatus.active;
      case 'ended':
        return LiveSessionStatus.ended;
      case 'expired':
        return LiveSessionStatus.expired;
      case 'cancelled':
        return LiveSessionStatus.cancelled;
      default:
        return LiveSessionStatus.unknown;
    }
  }
}

class LiveSession {
  const LiveSession({
    required this.uuid,
    required this.status,
    this.purpose,
    this.requestCamera = true,
    this.requestMicrophone = true,
    this.requesterName,
    this.requesterEmail,
    this.signalingToken,
    this.webrtcConfigured = false,
    this.iceServers,
    this.expiresAt,
    this.startedAt,
    this.endedAt,
  });

  final String uuid;
  final LiveSessionStatus status;
  final String? purpose;
  final bool requestCamera;
  final bool requestMicrophone;
  final String? requesterName;
  final String? requesterEmail;
  final String? signalingToken;
  final bool webrtcConfigured;
  final List<Map<String, dynamic>>? iceServers;
  final DateTime? expiresAt;
  final DateTime? startedAt;
  final DateTime? endedAt;

  factory LiveSession.fromJson(Map<String, dynamic> json) {
    final ice = json['ice_servers'];
    final requester = json['requester'];
    return LiveSession(
      uuid: json['uuid']?.toString() ?? json['id']?.toString() ?? '',
      status: LiveSessionStatus.fromString(json['status'] as String?),
      purpose: json['purpose'] as String?,
      requestCamera: json['request_camera'] as bool? ?? true,
      requestMicrophone: json['request_microphone'] as bool? ?? true,
      requesterName: requester is Map
          ? requester['name'] as String?
          : json['initiator'] as String?,
      requesterEmail:
          requester is Map ? requester['email'] as String? : null,
      signalingToken: json['signaling_token'] as String?,
      webrtcConfigured: json['webrtc_configured'] as bool? ?? false,
      iceServers: ice is List
          ? ice
              .whereType<Map>()
              .map((e) => Map<String, dynamic>.from(e))
              .toList()
          : null,
      expiresAt: json['expires_at'] != null
          ? DateTime.tryParse(json['expires_at'] as String)
          : null,
      startedAt: json['started_at'] != null
          ? DateTime.tryParse(json['started_at'] as String)
          : null,
      endedAt: json['ended_at'] != null
          ? DateTime.tryParse(json['ended_at'] as String)
          : null,
    );
  }
}
