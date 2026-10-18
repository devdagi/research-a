import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';

import '../authentication/app_state.dart';
import '../commands/commands_screen.dart';
import '../device_status/status_screen.dart';
import '../enrollment/privacy_screen.dart';
import '../live_session/live_session_screen.dart';
import '../location/location_screen.dart';
import '../notifications/notifications_screen.dart';
import '../../widgets/section_card.dart';

class DashboardScreen extends StatefulWidget {
  const DashboardScreen({super.key});

  @override
  State<DashboardScreen> createState() => _DashboardScreenState();
}

class _DashboardScreenState extends State<DashboardScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<AppState>().refreshStatus();
    });
  }

  @override
  Widget build(BuildContext context) {
    final appState = context.watch<AppState>();
    final status = appState.deviceStatus;
    final heartbeat = appState.heartbeatService.lastHeartbeatAt;
    final fmt = DateFormat.yMMMd().add_jm();

    return Scaffold(
      appBar: AppBar(
        title: Text(appState.deviceName ?? 'Device dashboard'),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh),
            onPressed: () => appState.refreshStatus(),
          ),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: () async {
          await appState.refreshStatus();
          await appState.pollLiveSessions();
        },
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            if (appState.lastError != null)
              Padding(
                padding: const EdgeInsets.only(bottom: 8),
                child: Text(
                  appState.lastError!,
                  style: TextStyle(
                    color: Theme.of(context).colorScheme.error,
                    fontSize: 12,
                  ),
                ),
              ),
            if (appState.activeLiveSession != null)
              Card(
                color: Theme.of(context).colorScheme.primaryContainer,
                child: ListTile(
                  leading: const Icon(Icons.mic),
                  title: const Text('Live session active'),
                  subtitle: Text(
                    appState.liveMedia.statusMessage ??
                        'Connected',
                  ),
                  trailing: const Icon(Icons.chevron_right),
                  onTap: () {
                    Navigator.of(context).push(
                      MaterialPageRoute<void>(
                        builder: (_) => const LiveSessionScreen(),
                      ),
                    );
                  },
                ),
              )
            else if (appState.pendingLiveSession != null)
              Card(
                color: Theme.of(context).colorScheme.primaryContainer,
                child: ListTile(
                  leading: const Icon(Icons.live_tv),
                  title: const Text('Live session ready'),
                  subtitle: const Text('Tap to connect'),
                  trailing: const Icon(Icons.chevron_right),
                  onTap: () {
                    Navigator.of(context).push(
                      MaterialPageRoute<void>(
                        builder: (_) => const LiveSessionScreen(),
                      ),
                    );
                  },
                ),
              ),
            SectionCard(
              title: 'Heartbeat',
              subtitle: heartbeat != null
                  ? 'Last sent ${fmt.format(heartbeat)}'
                  : 'Not sent yet',
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  if (appState.heartbeatService.lastError != null)
                    Text(
                      appState.heartbeatService.lastError!,
                      style: TextStyle(
                        color: Theme.of(context).colorScheme.error,
                        fontSize: 12,
                      ),
                    ),
                  FilledButton.tonal(
                    onPressed: () async {
                      try {
                        await appState.heartbeatService.sendHeartbeat();
                        setState(() {});
                      } catch (_) {
                        setState(() {});
                      }
                    },
                    child: const Text('Send heartbeat now'),
                  ),
                ],
              ),
            ),
            SectionCard(
              title: 'Device status',
              child: status == null
                  ? const Text('Collecting status…')
                  : Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text('Battery: ${status.batteryLevel}% (${status.batteryState})'),
                        Text('Connectivity: ${status.connectivity}'),
                        Text('Updated: ${fmt.format(status.lastUpdated)}'),
                      ],
                    ),
            ),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                _NavChip(
                  icon: Icons.location_on,
                  label: 'Location',
                  onTap: () => Navigator.push(
                    context,
                    MaterialPageRoute<void>(builder: (_) => const LocationScreen()),
                  ),
                ),
                _NavChip(
                  icon: Icons.signal_cellular_alt,
                  label: 'Status',
                  onTap: () => Navigator.push(
                    context,
                    MaterialPageRoute<void>(builder: (_) => const StatusScreen()),
                  ),
                ),
                _NavChip(
                  icon: Icons.notifications,
                  label: 'Notifications',
                  onTap: () => Navigator.push(
                    context,
                    MaterialPageRoute<void>(builder: (_) => const NotificationsScreen()),
                  ),
                ),
                _NavChip(
                  icon: Icons.terminal,
                  label: 'Commands',
                  onTap: () => Navigator.push(
                    context,
                    MaterialPageRoute<void>(builder: (_) => const CommandsScreen()),
                  ),
                ),
                _NavChip(
                  icon: Icons.live_tv,
                  label: 'Live session',
                  onTap: () => Navigator.push(
                    context,
                    MaterialPageRoute<void>(builder: (_) => const LiveSessionScreen()),
                  ),
                ),
                _NavChip(
                  icon: Icons.privacy_tip_outlined,
                  label: 'Privacy',
                  onTap: () => Navigator.push(
                    context,
                    MaterialPageRoute<void>(builder: (_) => const PrivacyScreen()),
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _NavChip extends StatelessWidget {
  const _NavChip({
    required this.icon,
    required this.label,
    required this.onTap,
  });

  final IconData icon;
  final String label;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return ActionChip(
      avatar: Icon(icon, size: 18),
      label: Text(label),
      onPressed: onTap,
    );
  }
}
