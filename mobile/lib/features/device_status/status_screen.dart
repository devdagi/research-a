import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/errors.dart' show ApiException;
import '../../widgets/section_card.dart';
import '../authentication/app_state.dart';

class StatusScreen extends StatefulWidget {
  const StatusScreen({super.key});

  @override
  State<StatusScreen> createState() => _StatusScreenState();
}

class _StatusScreenState extends State<StatusScreen> {
  bool _uploading = false;
  String? _message;

  Future<void> _upload() async {
    setState(() {
      _uploading = true;
      _message = null;
    });
    final appState = context.read<AppState>();
    try {
      await appState.statusService.uploadStatus();
      await appState.refreshStatus();
      setState(() => _message = 'Status uploaded to company server');
    } on ApiException catch (e) {
      setState(() => _message = e.message);
    } catch (e) {
      setState(() => _message = e.toString());
    } finally {
      if (mounted) setState(() => _uploading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final status = context.watch<AppState>().deviceStatus;

    return Scaffold(
      appBar: AppBar(title: const Text('Battery & connectivity')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          SectionCard(
            title: 'Current readings',
            child: status == null
                ? const Text('Loading…')
                : Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      ListTile(
                        contentPadding: EdgeInsets.zero,
                        leading: const Icon(Icons.battery_std),
                        title: Text('${status.batteryLevel}%'),
                        subtitle: Text('State: ${status.batteryState}'),
                      ),
                      ListTile(
                        contentPadding: EdgeInsets.zero,
                        leading: const Icon(Icons.wifi),
                        title: Text(status.connectivity),
                        subtitle: const Text('Network connectivity'),
                      ),
                    ],
                  ),
          ),
          if (_message != null) Text(_message!),
          FilledButton(
            onPressed: _uploading ? null : _upload,
            child: _uploading
                ? const SizedBox(
                    height: 20,
                    width: 20,
                    child: CircularProgressIndicator(strokeWidth: 2),
                  )
                : const Text('Upload status to server'),
          ),
        ],
      ),
    );
  }
}
