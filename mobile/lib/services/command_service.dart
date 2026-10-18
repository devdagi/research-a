import 'dart:async';

import '../core/config.dart';
import '../core/constants.dart';
import '../models/command.dart';
import 'api_client.dart';
import 'status_service.dart';

class CommandService {
  CommandService({
    ApiClient? apiClient,
    StatusService? statusService,
  })  : _api = apiClient ?? ApiClient(),
        _statusService = statusService ?? StatusService();

  final ApiClient _api;
  final StatusService _statusService;
  Timer? _pollTimer;

  Future<List<RemoteCommand>> fetchPending() async {
    final response = await _api.get(ApiPaths.commands);
    final list = response['data'] ?? response['commands'] ?? response;
    if (list is! List) return [];
    return list
        .whereType<Map>()
        .map((e) => RemoteCommand.fromJson(Map<String, dynamic>.from(e)))
        .toList();
  }

  Future<void> acknowledge(String uuid, {String status = 'acknowledged', Object? result}) async {
    await _api.post(
      ApiPaths.commandAck(uuid),
      body: {
        'status': status,
        if (result != null) 'result': result,
      },
    );
  }

  Future<void> execute(RemoteCommand command) async {
    try {
      switch (command.type) {
        case CommandType.checkIn:
        case CommandType.requestStatus:
        case CommandType.notify:
          await _statusService.uploadStatus();
          break;
        case CommandType.requestLocation:
        case CommandType.unknown:
          break;
      }
      await acknowledge(command.uuid, status: 'acknowledged');
    } catch (_) {
      await acknowledge(command.uuid, status: 'failed');
      rethrow;
    }
  }

  void startPolling({void Function(RemoteCommand)? onCommand}) {
    stopPolling();
    _pollTimer = Timer.periodic(AppConfig.commandPollInterval, (_) async {
      try {
        final commands = await fetchPending();
        for (final cmd in commands) {
          await execute(cmd);
          onCommand?.call(cmd);
        }
      } catch (_) {}
    });
  }

  void stopPolling() {
    _pollTimer?.cancel();
    _pollTimer = null;
  }
}
