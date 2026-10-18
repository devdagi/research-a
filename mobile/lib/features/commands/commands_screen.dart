import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';

import '../../core/errors.dart';
import '../../models/command.dart';
import '../authentication/app_state.dart';

class CommandsScreen extends StatefulWidget {
  const CommandsScreen({super.key});

  @override
  State<CommandsScreen> createState() => _CommandsScreenState();
}

class _CommandsScreenState extends State<CommandsScreen> {
  List<RemoteCommand> _commands = [];
  bool _loading = false;
  String? _error;

  Future<void> _refresh() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final list = await context.read<AppState>().commandService.fetchPending();
      setState(() => _commands = list);
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } catch (e) {
      setState(() => _error = e.toString());
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _runCheckIn() async {
    setState(() => _loading = true);
    try {
      await context.read<AppState>().statusService.uploadStatus();
      await context.read<AppState>().heartbeatService.sendHeartbeat();
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Check-in completed')),
        );
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(e.toString())),
        );
      }
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  void initState() {
    super.initState();
    _refresh();
  }

  @override
  Widget build(BuildContext context) {
    final fmt = DateFormat.MMMd().add_jm();

    return Scaffold(
      appBar: AppBar(
        title: const Text('Remote commands'),
        actions: [
          IconButton(onPressed: _refresh, icon: const Icon(Icons.refresh)),
        ],
      ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: _loading ? null : _runCheckIn,
        icon: const Icon(Icons.check),
        label: const Text('Check in'),
      ),
      body: _loading && _commands.isEmpty
          ? const Center(child: CircularProgressIndicator())
          : ListView(
              padding: const EdgeInsets.all(16),
              children: [
                Text(
                  'Your employer may send remote check-in or status commands. '
                  'Each command is acknowledged after processing.',
                ),
                if (_error != null) ...[
                  const SizedBox(height: 8),
                  Text(_error!, style: TextStyle(color: Theme.of(context).colorScheme.error)),
                ],
                const SizedBox(height: 16),
                if (_commands.isEmpty)
                  const Text('No pending commands')
                else
                  ..._commands.map(
                    (c) => Card(
                      child: ListTile(
                        title: Text(c.type.name),
                        subtitle: Text(
                          c.createdAt != null
                              ? 'Issued ${fmt.format(c.createdAt!)}'
                              : c.uuid,
                        ),
                        trailing: IconButton(
                          icon: const Icon(Icons.play_arrow),
                          onPressed: () async {
                            await context.read<AppState>().commandService.execute(c);
                            _refresh();
                          },
                        ),
                      ),
                    ),
                  ),
              ],
            ),
    );
  }
}
