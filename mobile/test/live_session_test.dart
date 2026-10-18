import 'dart:convert';

import 'package:company_device_manager/models/live_session.dart';
import 'package:company_device_manager/services/api_client.dart';
import 'package:company_device_manager/services/live_session_service.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';

import 'fakes.dart';

void main() {
  group('LiveSessionService', () {
    test('approve parses session response', () async {
      final api = ApiClient(
        baseUrl: 'https://example.test',
        httpClient: MockClient((request) async {
          expect(request.url.path, endsWith('/respond'));
          expect(jsonDecode(request.body)['decision'], 'approved');
          return http.Response(
            jsonEncode({
              'data': {
                'uuid': 'sess-7',
                'status': 'approved',
                'webrtc_configured': false,
              },
            }),
            200,
            headers: {'content-type': 'application/json'},
          );
        }),
        authStorage: MemoryAuthStorage('t'),
      );
      final service = LiveSessionService(apiClient: api);

      final session = await service.approve('sess-7');
      expect(session.uuid, 'sess-7');
      expect(session.status, LiveSessionStatus.approved);
      api.close();
    });

    test('reject calls respond endpoint with denied', () async {
      String? path;
      Map<String, dynamic>? body;
      final api = ApiClient(
        baseUrl: 'https://example.test',
        httpClient: MockClient((request) async {
          path = request.url.path;
          body = jsonDecode(request.body) as Map<String, dynamic>;
          return http.Response(
            jsonEncode({
              'data': {'uuid': 'sess-3', 'status': 'denied'},
            }),
            200,
            headers: {'content-type': 'application/json'},
          );
        }),
        authStorage: MemoryAuthStorage('t'),
      );
      final service = LiveSessionService(apiClient: api);

      await service.reject('sess-3', reason: 'Not now');
      expect(path, endsWith('/respond'));
      expect(body?['decision'], 'denied');
      api.close();
    });
  });
}
