import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';

import '../../models/notification.dart';
import '../authentication/app_state.dart';

class NotificationsScreen extends StatefulWidget {
  const NotificationsScreen({super.key});

  @override
  State<NotificationsScreen> createState() => _NotificationsScreenState();
}

class _NotificationsScreenState extends State<NotificationsScreen> {
  List<AppNotification> _items = [];
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    final service = context.read<AppState>().notificationService;
    final list = await service.loadHistory();
    if (!mounted) return;
    setState(() {
      _items = list;
      _loading = false;
    });
  }

  @override
  Widget build(BuildContext context) {
    final fmt = DateFormat.MMMd().add_jm();

    return Scaffold(
      appBar: AppBar(title: const Text('Notification history')),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _items.isEmpty
              ? const Center(child: Text('No notifications yet'))
              : ListView.separated(
                  itemCount: _items.length,
                  separatorBuilder: (_, __) => const Divider(height: 1),
                  itemBuilder: (context, index) {
                    final n = _items[index];
                    return ListTile(
                      title: Text(n.title),
                      subtitle: Text('${n.body}\n${fmt.format(n.receivedAt)}'),
                      isThreeLine: true,
                      trailing: n.read
                          ? null
                          : const Icon(Icons.fiber_manual_record, size: 12),
                      onTap: () async {
                        await context
                            .read<AppState>()
                            .notificationService
                            .markRead(n.id);
                        _load();
                      },
                    );
                  },
                ),
    );
  }
}
