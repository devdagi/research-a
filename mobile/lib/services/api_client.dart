import 'dart:convert';
import 'dart:io';

import 'package:http/http.dart' as http;
import 'package:http/io_client.dart';

import '../core/config.dart';
import '../core/errors.dart';
import 'auth_storage.dart';

class ApiClient {
  ApiClient({
    AuthStorage? authStorage,
    http.Client? httpClient,
    String? baseUrl,
    this.fixedToken,
  })  : _authStorage = authStorage ?? AuthStorage(),
        _http = httpClient ?? _createDirectClient(),
        _baseUrl = baseUrl ?? AppConfig.apiBaseUrl;

  final AuthStorage _authStorage;
  final http.Client _http;
  final String _baseUrl;
  /// Used by the FGS isolate after reboot (secure storage may not be ready).
  final String? fixedToken;

  /// Bypass system/VPN HTTP proxies that break emulator → host (10.0.2.2).
  static http.Client _createDirectClient() {
    final inner = HttpClient();
    inner.connectionTimeout = const Duration(seconds: 15);
    inner.idleTimeout = const Duration(seconds: 15);
    inner.findProxy = (_) => 'DIRECT';
    return IOClient(inner);
  }

  Uri _uri(String path) {
    final normalized = path.startsWith('/') ? path : '/$path';
    return Uri.parse('$_baseUrl$normalized');
  }

  Future<Map<String, String>> _headers({bool authenticated = true}) async {
    final headers = <String, String>{
      'Accept': 'application/json',
      'Content-Type': 'application/json',
    };
    if (authenticated) {
      final token = (fixedToken != null && fixedToken!.isNotEmpty)
          ? fixedToken
          : await _authStorage.getDeviceToken();
      if (token != null && token.isNotEmpty) {
        headers['Authorization'] = 'Bearer $token';
      }
    }
    return headers;
  }

  Future<Map<String, dynamic>> get(
    String path, {
    bool authenticated = true,
  }) async {
    final response = await _http.get(
      _uri(path),
      headers: await _headers(authenticated: authenticated),
    );
    return _decodeResponse(response);
  }

  Future<Map<String, dynamic>> post(
    String path, {
    Map<String, dynamic>? body,
    bool authenticated = true,
  }) async {
    final response = await _http.post(
      _uri(path),
      headers: await _headers(authenticated: authenticated),
      body: body == null ? null : jsonEncode(body),
    );
    return _decodeResponse(response);
  }

  Map<String, dynamic> _decodeResponse(http.Response response) {
    Map<String, dynamic>? parsed;
    if (response.body.isNotEmpty) {
      final decoded = jsonDecode(response.body);
      if (decoded is Map<String, dynamic>) {
        parsed = decoded;
      }
    }

    if (response.statusCode >= 200 && response.statusCode < 300) {
      return parsed ?? <String, dynamic>{};
    }

    final message = parsed?['message'] as String? ??
        parsed?['error'] as String? ??
        'Request failed';
    throw ApiException(
      message: message,
      statusCode: response.statusCode,
      body: response.body.isEmpty ? null : response.body,
    );
  }

  void close() => _http.close();
}
