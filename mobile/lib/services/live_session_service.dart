import '../core/config.dart';
import '../core/constants.dart';
import '../models/live_session.dart';
import 'api_client.dart';

class LiveSessionService {
  LiveSessionService({ApiClient? apiClient}) : _api = apiClient ?? ApiClient();

  final ApiClient _api;

  Future<List<LiveSession>> fetchPendingSessions() async {
    final response = await _api.get(ApiPaths.liveSessionsPending);
    return _parseList(response);
  }

  List<LiveSession> _parseList(Map<String, dynamic> response) {
    final list = response['data'] ?? response['sessions'] ?? response;
    if (list is! List) return [];
    return list
        .whereType<Map>()
        .map((e) => LiveSession.fromJson(Map<String, dynamic>.from(e)))
        .toList();
  }

  Future<LiveSession> approve(String sessionUuid) async {
    final response = await _api.post(
      ApiPaths.liveSessionRespond(sessionUuid),
      body: {'decision': 'approved'},
    );
    final data = response['data'] is Map<String, dynamic>
        ? response['data'] as Map<String, dynamic>
        : response;
    return LiveSession.fromJson(data);
  }

  Future<LiveSession> reject(String sessionUuid, {String? reason}) async {
    final response = await _api.post(
      ApiPaths.liveSessionRespond(sessionUuid),
      body: {
        'decision': 'denied',
        if (reason != null) 'notes': reason,
      },
    );
    final data = response['data'] is Map<String, dynamic>
        ? response['data'] as Map<String, dynamic>
        : response;
    return LiveSession.fromJson(data);
  }

  Future<LiveSession> startSession(String sessionUuid) async {
    final response = await _api.post(ApiPaths.liveSessionStart(sessionUuid));
    final data = response['data'] is Map<String, dynamic>
        ? response['data'] as Map<String, dynamic>
        : response;
    return LiveSession.fromJson(data);
  }

  Future<void> endSession(String sessionUuid) async {
    await _api.post(ApiPaths.liveSessionEnd(sessionUuid));
  }

  Future<void> postOffer(
    String sessionUuid, {
    required String offerSdp,
    List<Map<String, dynamic>> ice = const [],
  }) async {
    await _api.post(
      ApiPaths.liveSessionOffer(sessionUuid),
      body: {
        'offer_sdp': offerSdp,
        'ice': ice,
      },
    );
  }

  Future<void> postIceCandidate(
    String sessionUuid,
    Map<String, dynamic> candidate,
  ) async {
    await _api.post(
      ApiPaths.liveSessionIce(sessionUuid),
      body: {'candidate': candidate},
    );
  }

  Future<Map<String, dynamic>> fetchSignaling(String sessionUuid) async {
    final response = await _api.get(ApiPaths.liveSessionSignaling(sessionUuid));
    final data = response['data'];
    if (data is Map<String, dynamic>) return data;
    if (data is Map) return Map<String, dynamic>.from(data);
    return response;
  }

  List<Map<String, dynamic>> defaultIceServers(LiveSession session) {
    if (session.iceServers != null && session.iceServers!.isNotEmpty) {
      return session.iceServers!;
    }
    final servers = <Map<String, dynamic>>[
      {'urls': 'stun:stun.l.google.com:19302'},
    ];
    if (AppConfig.isTurnConfigured) {
      servers.add({
        'urls': AppConfig.turnUrl,
        'username': AppConfig.turnUsername,
        'credential': AppConfig.turnCredential,
      });
    }
    return servers;
  }
}
