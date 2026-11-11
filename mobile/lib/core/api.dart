import 'dart:convert';

import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:http/http.dart' as http;
import 'package:uuid/uuid.dart';

/// Android emulator reaches the host machine on 10.0.2.2. Override with
/// --dart-define=API_URL=https://api.haske.ng
const apiUrl = String.fromEnvironment('API_URL', defaultValue: 'http://10.0.2.2:8000');

class ApiException implements Exception {
  ApiException(this.message, {this.status = 0, this.code, this.fields = const {}});

  final String message;
  final int status;
  final String? code;
  final Map<String, String> fields;

  bool get unauthenticated => status == 401;

  @override
  String toString() => message;
}

/// Bearer token plus X-Device-Id on every call: the server rejects a token used from another device.
class Api {
  Api({http.Client? client, FlutterSecureStorage? storage})
      : _http = client ?? http.Client(),
        _store = storage ?? const FlutterSecureStorage();

  final http.Client _http;
  final FlutterSecureStorage _store;
  String? _token;
  String? _deviceId;

  Future<void> init() async {
    _token = await _store.read(key: 'token');
    _deviceId = await _store.read(key: 'device_id');
    if (_deviceId == null) {
      _deviceId = const Uuid().v4();
      await _store.write(key: 'device_id', value: _deviceId);
    }
  }

  bool get hasToken => _token != null;
  String get deviceId => _deviceId!;

  Future<void> setToken(String? t) async {
    _token = t;
    t == null ? await _store.delete(key: 'token') : await _store.write(key: 'token', value: t);
  }

  Map<String, String> get _headers => {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-Device-Id': _deviceId ?? '',
        if (_token != null) 'Authorization': 'Bearer $_token',
      };

  Future<dynamic> get(String path) => _send(() => _http.get(Uri.parse('$apiUrl/api/v1$path'), headers: _headers));

  Future<dynamic> post(String path, [Map<String, dynamic>? body]) =>
      _send(() => _http.post(Uri.parse('$apiUrl/api/v1$path'), headers: _headers, body: jsonEncode(body ?? {})));

  Future<dynamic> patch(String path, Map<String, dynamic> body) =>
      _send(() => _http.patch(Uri.parse('$apiUrl/api/v1$path'), headers: _headers, body: jsonEncode(body)));

  Future<dynamic> delete(String path) => _send(() => _http.delete(Uri.parse('$apiUrl/api/v1$path'), headers: _headers));

  Future<dynamic> _send(Future<http.Response> Function() call) async {
    http.Response res;
    try {
      // Purchases can wait on the provider for up to 45 s server-side.
      res = await call().timeout(const Duration(seconds: 70));
    } catch (_) {
      throw ApiException('No connection. Check your data and try again.');
    }
    final body = res.body.isEmpty ? null : jsonDecode(res.body);
    if (res.statusCode >= 200 && res.statusCode < 300) return body;

    final map = body is Map ? body : const {};
    final fields = <String, String>{};
    if (map['errors'] is Map) {
      (map['errors'] as Map).forEach((k, v) => fields[k as String] = v is List && v.isNotEmpty ? '${v.first}' : '$v');
    }
    throw ApiException(
      fields.values.firstOrNull ?? map['message'] as String? ?? map['error'] as String? ?? 'Something went wrong (${res.statusCode}).',
      status: res.statusCode,
      code: map['code'] as String?,
      fields: fields,
    );
  }
}
