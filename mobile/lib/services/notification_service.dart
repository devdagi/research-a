import 'dart:convert';

import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import '../models/notification.dart';

const _historyKey = 'notification_history_v1';

class NotificationService {
  NotificationService({FlutterSecureStorage? storage})
      : _storage = storage ?? const FlutterSecureStorage();

  final FlutterSecureStorage _storage;

  Future<List<AppNotification>> loadHistory() async {
    final raw = await _storage.read(key: _historyKey);
    if (raw == null || raw.isEmpty) return [];
    final decoded = jsonDecode(raw);
    if (decoded is! List) return [];
    return decoded
        .whereType<Map>()
        .map((e) => AppNotification.fromJson(Map<String, dynamic>.from(e)))
        .toList()
      ..sort((a, b) => b.receivedAt.compareTo(a.receivedAt));
  }

  Future<void> add(AppNotification notification) async {
    // App does not send or store push/local notification alerts.
  }

  Future<void> markRead(String id) async {
    final list = await loadHistory();
    final updated = list
        .map((n) => n.id == id ? n.copyWith(read: true) : n)
        .toList();
    await _persist(updated);
  }

  Future<void> _persist(List<AppNotification> list) async {
    await _storage.write(
      key: _historyKey,
      value: jsonEncode(list.map((e) => {
            'id': e.id,
            'title': e.title,
            'body': e.body,
            'received_at': e.receivedAt.toIso8601String(),
            'read': e.read,
          }).toList()),
    );
  }
}
