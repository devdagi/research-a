import 'dart:convert';

import 'package:company_device_manager/core/errors.dart';
import 'package:company_device_manager/services/api_client.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';

import 'fakes.dart';

void main() {
  group('ApiClient', () {
    test('throws ApiException on error responses', () async {
      final client = ApiClient(
        baseUrl: 'https://example.test',
        httpClient: MockClient((request) async {
          return http.Response(
            jsonEncode({'message': 'Invalid enrollment code'}),
            422,
            headers: {'content-type': 'application/json'},
          );
        }),
        authStorage: MemoryAuthStorage('token'),
      );

      expect(
        () => client.post('/api/v1/device/enroll', body: {'code': 'X'}),
        throwsA(
          isA<ApiException>().having((e) => e.statusCode, 'status', 422),
        ),
      );
      client.close();
    });

    test('decodes successful JSON body', () async {
      final client = ApiClient(
        baseUrl: 'https://example.test',
        httpClient: MockClient((request) async {
          expect(request.headers['Authorization'], 'Bearer device-token');
          return http.Response('{"ok":true}', 200);
        }),
        authStorage: MemoryAuthStorage('device-token'),
      );

      final result = await client.get('/api/v1/device/commands');
      expect(result['ok'], isTrue);
      client.close();
    });
  });
}
