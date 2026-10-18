import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../widgets/section_card.dart';
import '../authentication/app_state.dart';

class PrivacyScreen extends StatelessWidget {
  const PrivacyScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final retention =
        context.watch<AppState>().consent?.dataRetentionDays ?? 90;

    return Scaffold(
      appBar: AppBar(title: const Text('Privacy & data retention')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          SectionCard(
            title: 'What we collect',
            child: const Text(
              'Opening the app registers this phone with your organization. The app may send '
              'heartbeat timestamps, battery/connectivity status, location, '
              'and live session media when the admin starts a session.',
            ),
          ),
          SectionCard(
            title: 'Data retention',
            child: Text(
              'Your organization\'s policy applies to server-side retention. '
              'Default reference period: $retention days (configured by admin API). '
              'Local notification history is kept on-device (up to 100 items) until you remove the device.',
            ),
          ),
          SectionCard(
            title: 'Remove this device',
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                const Text(
                  'Removes the device token from secure storage and stops background heartbeat/command polling. '
                  'Reopening the app will register again automatically.',
                ),
                const SizedBox(height: 12),
                FilledButton(
                  style: FilledButton.styleFrom(
                    backgroundColor: Theme.of(context).colorScheme.error,
                    foregroundColor: Theme.of(context).colorScheme.onError,
                  ),
                  onPressed: () async {
                    final confirm = await showDialog<bool>(
                      context: context,
                      builder: (ctx) => AlertDialog(
                        title: const Text('Remove device?'),
                        content: const Text(
                          'This phone will disconnect. Opening the app again registers it automatically.',
                        ),
                        actions: [
                          TextButton(
                            onPressed: () => Navigator.pop(ctx, false),
                            child: const Text('Cancel'),
                          ),
                          FilledButton(
                            onPressed: () => Navigator.pop(ctx, true),
                            child: const Text('Remove'),
                          ),
                        ],
                      ),
                    );
                    if (confirm != true || !context.mounted) return;
                    final appState = context.read<AppState>();
                    await appState.unenroll();
                    if (!context.mounted) return;
                    Navigator.of(context).popUntil((route) => route.isFirst);
                    await appState.retryAutoEnroll();
                  },
                  child: const Text('Remove device'),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
