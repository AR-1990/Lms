import 'dart:async';
import 'dart:convert';
import 'dart:io';

import 'package:http/http.dart' as http;
import 'package:path_provider/path_provider.dart';

/// An API call that came back with success = false (or never reached the server).
class ApiException implements Exception {
  ApiException(this.message, {this.status = 0, this.fields = const {}});

  final String message;
  final int status;

  /// Validation errors per field, from errors.fields.
  final Map<String, List<String>> fields;

  String? field(String key) => fields[key]?.isNotEmpty == true ? fields[key]!.first : null;

  @override
  String toString() => message;
}

/// Talks to one school's server. Every response is the envelope { success, message, data, errors };
/// calls return `data` and throw [ApiException] when success is false.
class ApiClient {
  ApiClient({required this.baseUrl, this.token, this.onUnauthorized});

  String baseUrl;
  String? token;
  void Function()? onUnauthorized;

  static const _timeout = Duration(seconds: 25);

  /// Turns "school.com", "https://school.com/" or "https://school.com/api" into "https://school.com".
  static String normalizeBaseUrl(String input) {
    var url = input.trim();
    if (url.isEmpty) {
      return url;
    }
    if (!url.startsWith('http://') && !url.startsWith('https://')) {
      url = 'https://$url';
    }
    while (url.endsWith('/')) {
      url = url.substring(0, url.length - 1);
    }
    if (url.endsWith('/api')) {
      url = url.substring(0, url.length - 4);
    }
    return url;
  }

  /// Paths starting with "http" are used as-is (signed file links); others are under {baseUrl}/api/.
  Uri uri(String path, [Map<String, dynamic>? query]) {
    final full = path.startsWith('http') ? path : '$baseUrl/api/${path.startsWith('/') ? path.substring(1) : path}';
    final parsed = Uri.parse(full);
    if (query == null || query.isEmpty) {
      return parsed;
    }
    final params = <String, String>{...parsed.queryParameters};
    query.forEach((key, value) {
      if (value != null && value.toString().isNotEmpty) {
        params[key] = value.toString();
      }
    });
    return parsed.replace(queryParameters: params);
  }

  Map<String, String> get headers => {
        'Accept': 'application/json',
        if (token != null) 'Authorization': 'Bearer $token',
      };

  Future<dynamic> get(String path, {Map<String, dynamic>? query}) => _send(() => http.get(uri(path, query), headers: headers));

  Future<dynamic> post(String path, [Map<String, dynamic>? body]) => _send(() => http.post(uri(path), headers: {...headers, 'Content-Type': 'application/json'}, body: jsonEncode(body ?? {})));

  Future<dynamic> put(String path, [Map<String, dynamic>? body]) => _send(() => http.put(uri(path), headers: {...headers, 'Content-Type': 'application/json'}, body: jsonEncode(body ?? {})));

  Future<dynamic> patch(String path, [Map<String, dynamic>? body]) => _send(() => http.patch(uri(path), headers: {...headers, 'Content-Type': 'application/json'}, body: jsonEncode(body ?? {})));

  Future<dynamic> delete(String path, [Map<String, dynamic>? body]) => _send(() => http.delete(uri(path), headers: {...headers, 'Content-Type': 'application/json'}, body: jsonEncode(body ?? {})));

  /// Form upload. [files] maps the field name (e.g. "files[]" or "screenshot") to the files to send.
  Future<dynamic> upload(String path, {Map<String, String> fields = const {}, Map<String, List<File>> files = const {}, String method = 'POST'}) {
    return _send(() async {
      final request = http.MultipartRequest(method, uri(path))
        ..headers.addAll(headers)
        ..fields.addAll(fields);
      for (final entry in files.entries) {
        for (final file in entry.value) {
          request.files.add(await http.MultipartFile.fromPath(entry.key, file.path));
        }
      }
      return http.Response.fromStream(await request.send());
    });
  }

  /// Downloads a file (PDF, image) with the sign-in token and saves it in the app's temporary folder.
  Future<File> download(String path, String fileName, {Map<String, dynamic>? query}) async {
    http.Response response;
    try {
      response = await http.get(uri(path, query), headers: {...headers, 'Accept': '*/*'}).timeout(const Duration(seconds: 60));
    } on SocketException {
      throw ApiException('No internet connection. Please try again.');
    } on TimeoutException {
      throw ApiException('The download is taking too long. Please try again.');
    }
    if (response.statusCode == 401) {
      onUnauthorized?.call();
      throw ApiException('Please sign in again.', status: 401);
    }
    if (response.statusCode >= 400 || (response.headers['content-type'] ?? '').contains('application/json')) {
      _decode(response);
      throw ApiException('The file could not be opened.', status: response.statusCode);
    }
    final directory = await getTemporaryDirectory();
    final file = File('${directory.path}/${fileName.replaceAll(RegExp(r'[^A-Za-z0-9._-]'), '_')}');
    await file.writeAsBytes(response.bodyBytes);
    return file;
  }

  Future<dynamic> _send(Future<http.Response> Function() call) async {
    http.Response response;
    try {
      response = await call().timeout(_timeout);
    } on SocketException {
      throw ApiException('No internet connection. Please try again.');
    } on HandshakeException {
      throw ApiException('Secure connection to the school server failed.');
    } on http.ClientException {
      throw ApiException('Could not reach the school server.');
    } on TimeoutException {
      throw ApiException('The school server is taking too long. Please try again.');
    }
    if (response.statusCode == 401 && token != null) {
      onUnauthorized?.call();
    }
    return _decode(response);
  }

  dynamic _decode(http.Response response) {
    dynamic body;
    try {
      body = jsonDecode(utf8.decode(response.bodyBytes));
    } catch (_) {
      throw ApiException(response.statusCode >= 500 ? 'The school server had a problem. Please try later.' : 'Unexpected answer from the server (${response.statusCode}).', status: response.statusCode);
    }
    if (body is Map && body['success'] == true) {
      return body['data'];
    }
    final fields = <String, List<String>>{};
    final errors = body is Map ? body['errors'] : null;
    final rawFields = errors is Map ? (errors['fields'] ?? errors) : null;
    if (rawFields is Map) {
      rawFields.forEach((key, value) {
        if (value is List) {
          fields[key.toString()] = value.map((item) => item.toString()).toList();
        }
      });
    }
    final summary = errors is Map ? errors['summary'] : null;
    final message = (summary is String && summary.isNotEmpty) ? summary : (body is Map && body['message'] is String ? body['message'] as String : 'Something went wrong.');
    throw ApiException(message, status: response.statusCode, fields: fields);
  }
}
