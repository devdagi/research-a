class AppNotification {
  const AppNotification({
    required this.id,
    required this.title,
    required this.body,
    required this.receivedAt,
    this.read = false,
  });

  final String id;
  final String title;
  final String body;
  final DateTime receivedAt;
  final bool read;

  AppNotification copyWith({bool? read}) => AppNotification(
        id: id,
        title: title,
        body: body,
        receivedAt: receivedAt,
        read: read ?? this.read,
      );

  factory AppNotification.fromJson(Map<String, dynamic> json) {
    return AppNotification(
      id: json['id']?.toString() ?? '',
      title: json['title'] as String? ?? 'Notification',
      body: json['body'] as String? ?? json['message'] as String? ?? '',
      receivedAt: json['received_at'] != null
          ? DateTime.tryParse(json['received_at'] as String) ?? DateTime.now()
          : DateTime.now(),
      read: json['read'] as bool? ?? false,
    );
  }
}
