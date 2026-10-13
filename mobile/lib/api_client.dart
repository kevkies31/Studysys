import 'dart:convert';

import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:http/http.dart' as http;

class ApiException implements Exception {
  const ApiException(this.message);

  final String message;

  @override
  String toString() => message;
}

class ApiClient {
  ApiClient._();

  static const _baseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'http://10.0.2.2/Studier/api',
  );
  static const _storage = FlutterSecureStorage();
  static const _tokenKey = 'studysys_api_token';
  static const _themeKey = 'studysys_theme_mode';
  static final _http = http.Client();
  static void Function()? onUnauthorized;

  static Future<String?> readToken() => _storage.read(key: _tokenKey);

  static Future<void> saveToken(String token) =>
      _storage.write(key: _tokenKey, value: token);

  static Future<void> clearToken() => _storage.delete(key: _tokenKey);

  static Future<String?> readThemeMode() => _storage.read(key: _themeKey);

  static Future<void> saveThemeMode(String mode) =>
      _storage.write(key: _themeKey, value: mode);

  static Future<dynamic> get(String path, {Map<String, String>? query}) async {
    return _send('GET', path, query: query);
  }

  static Future<dynamic> post(String path, Map<String, dynamic> body) async {
    return _send('POST', path, body: body);
  }

  static Future<dynamic> _send(
    String method,
    String path, {
    Map<String, String>? query,
    Map<String, dynamic>? body,
  }) async {
    final uri = Uri.parse('$_baseUrl/$path').replace(queryParameters: query);
    final token = await readToken();
    final headers = <String, String>{'Accept': 'application/json'};
    if (body != null) headers['Content-Type'] = 'application/json';
    if (token != null) {
      headers['Authorization'] = 'Bearer $token';
      headers['X-StudySys-Token'] = token;
    }

    late final http.Response response;
    try {
      response = method == 'GET'
          ? await _http.get(uri, headers: headers)
          : await _http.post(uri, headers: headers, body: jsonEncode(body));
    } on Exception catch (error) {
      throw ApiException('Could not reach the StudySys server: $error');
    }

    dynamic decoded;
    try {
      decoded = jsonDecode(response.body);
    } on FormatException {
      throw const ApiException(
        'The server returned an invalid response. Check the API URL and PHP server.',
      );
    }
    if (response.statusCode == 401) {
      await clearToken();
      onUnauthorized?.call();
    }
    if (response.statusCode < 200 || response.statusCode >= 300) {
      throw ApiException(
        decoded is Map<String, dynamic>
            ? (decoded['error'] as String? ?? 'Request failed.')
            : 'Request failed with status ${response.statusCode}.',
      );
    }
    return decoded is Map<String, dynamic> ? decoded['data'] : null;
  }
}
