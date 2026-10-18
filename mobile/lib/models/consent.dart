class ConsentSettings {
  const ConsentSettings({
    required this.locationSharing,
    required this.notifications,
    required this.liveSession,
    this.dataRetentionDays,
    this.updatedAt,
  });

  final bool locationSharing;
  final bool notifications;
  final bool liveSession;
  final int? dataRetentionDays;
  final DateTime? updatedAt;

  ConsentSettings copyWith({
    bool? locationSharing,
    bool? notifications,
    bool? liveSession,
    int? dataRetentionDays,
    DateTime? updatedAt,
  }) {
    return ConsentSettings(
      locationSharing: locationSharing ?? this.locationSharing,
      notifications: notifications ?? this.notifications,
      liveSession: liveSession ?? this.liveSession,
      dataRetentionDays: dataRetentionDays ?? this.dataRetentionDays,
      updatedAt: updatedAt ?? this.updatedAt,
    );
  }

  Map<String, dynamic> toJson() => {
        'location_sharing': locationSharing,
        'notifications': notifications,
        'live_session': liveSession,
        if (dataRetentionDays != null) 'data_retention_days': dataRetentionDays,
      };

  factory ConsentSettings.fromJson(Map<String, dynamic> json) {
    return ConsentSettings(
      locationSharing: json['location_sharing'] as bool? ?? false,
      notifications: json['notifications'] as bool? ?? false,
      liveSession: json['live_session'] as bool? ?? false,
      dataRetentionDays: json['data_retention_days'] as int?,
      updatedAt: json['updated_at'] != null
          ? DateTime.tryParse(json['updated_at'] as String)
          : null,
    );
  }
}
