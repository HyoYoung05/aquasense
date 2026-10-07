import 'dart:async';
import 'dart:convert';
import 'package:flutter/foundation.dart';
import 'package:http/http.dart' as http;

import '../config/api_config.dart';

enum ApiErrorType {
  configurationError,
  networkError,
  serverUnavailable,
  unauthorized,
  forbidden,
  notFound,
  validationError,
  rateLimited,
  serverError,
}

extension ApiErrorTypeCode on ApiErrorType {
  String get code => switch (this) {
    ApiErrorType.configurationError => 'CONFIGURATION_ERROR',
    ApiErrorType.networkError => 'NETWORK_ERROR',
    ApiErrorType.serverUnavailable => 'SERVER_UNAVAILABLE',
    ApiErrorType.unauthorized => 'UNAUTHORIZED',
    ApiErrorType.forbidden => 'FORBIDDEN',
    ApiErrorType.notFound => 'NOT_FOUND',
    ApiErrorType.validationError => 'VALIDATION_ERROR',
    ApiErrorType.rateLimited => 'RATE_LIMITED',
    ApiErrorType.serverError => 'SERVER_ERROR',
  };
}

class ApiException implements Exception {
  final ApiErrorType type;
  final String message;
  final int? status;

  const ApiException(this.message, {required this.type, this.status});

  const ApiException.unauthorized([
    this.message = 'Your session has expired. Please sign in again.',
  ]) : type = ApiErrorType.unauthorized,
       status = 401;

  @override
  String toString() => message;
}

class ApiService {
  final http.Client _client;
  final String _baseUrl;
  final Duration _timeout;

  ApiService({
    http.Client? client,
    String? baseUrl,
    bool releaseMode = kReleaseMode,
    Duration timeout = ApiConfig.timeout,
  }) : _baseUrl = ApiConfig.validatedBaseUrl(
         baseUrl ?? ApiConfig.configuredBaseUrl,
         releaseMode: releaseMode,
       ),
       _timeout = timeout,
       _client = client ?? http.Client();

  String get baseUrl => _baseUrl;

  static String connectionFailureMessage({
    bool debugMode = kDebugMode,
    bool web = kIsWeb,
  }) {
    if (debugMode && web) {
      return 'The browser could not reach the service. Check API_BASE_URL and the server CORS/OPTIONS response.';
    }
    return 'Unable to connect to the AQUASENSE+ server.';
  }

  void close() => _client.close();

  Future<Map<String, dynamic>> get(String path, {String? token}) =>
      request(path, token: token);

  Future<Map<String, dynamic>> post(
    String path, {
    String? token,
    Map<String, dynamic>? body,
  }) => request(path, token: token, body: body, post: true);

  Future<Map<String, dynamic>> postMultipart(
    String path, {
    required String token,
    required Map<String, String> fields,
    required String fileField,
    required String filePath,
    required String filename,
    Uint8List? fileBytes,
  }) async {
    final stopwatch = Stopwatch()..start();
    int? status;
    ApiErrorType? category;
    try {
      final request = http.MultipartRequest(
        'POST',
        ApiConfig.validatedEndpoint(_baseUrl, path),
      )..followRedirects = false;
      request.headers['Accept'] = 'application/json';
      request.headers['Authorization'] = 'Bearer $token';
      request.fields.addAll(fields);
      request.files.add(
        fileBytes == null
            ? await http.MultipartFile.fromPath(
                fileField,
                filePath,
                filename: filename,
              )
            : http.MultipartFile.fromBytes(
                fileField,
                fileBytes,
                filename: filename,
              ),
      );
      final response = await _client
          .send(request)
          .then(http.Response.fromStream)
          .timeout(const Duration(seconds: 60));
      status = response.statusCode;
      Map<String, dynamic> decoded;
      try {
        decoded = _decodeObject(response.body);
      } on FormatException {
        if (status >= 200 && status < 300) rethrow;
        decoded = <String, dynamic>{};
      }
      if (status < 200 || status >= 300) {
        throw _httpException(status, _safeServerMessage(decoded));
      }
      if (decoded['success'] != true ||
          decoded['data'] is! Map<String, dynamic>) {
        throw const FormatException('Invalid response envelope.');
      }
      return decoded['data'] as Map<String, dynamic>;
    } on ApiConfigurationException catch (error) {
      category = ApiErrorType.configurationError;
      throw ApiException(error.message, type: category);
    } on ApiException catch (error) {
      category = error.type;
      rethrow;
    } on TimeoutException {
      category = ApiErrorType.networkError;
      throw const ApiException(
        'Submission status could not be confirmed. Refresh your surrender history before submitting again.',
        type: ApiErrorType.networkError,
      );
    } on FormatException {
      category = ApiErrorType.serverError;
      throw const ApiException(
        'The server returned an unreadable response. Please try again.',
        type: ApiErrorType.serverError,
      );
    } on http.ClientException {
      category = ApiErrorType.networkError;
      throw const ApiException(
        'Unable to submit right now. Please try again.',
        type: ApiErrorType.networkError,
      );
    } catch (_) {
      category = ApiErrorType.networkError;
      throw const ApiException(
        'Unable to submit right now. Please try again.',
        type: ApiErrorType.networkError,
      );
    } finally {
      if (kDebugMode) {
        debugPrint(
          'API MULTIPART $path status=${status ?? '-'} '
          'duration=${stopwatch.elapsedMilliseconds}ms '
          'category=${category?.code ?? 'OK'}',
        );
      }
    }
  }

  Future<Uint8List> getBytes(String path, {required String token}) async {
    try {
      final request = http.Request(
        'GET',
        ApiConfig.validatedEndpoint(_baseUrl, path),
      )..followRedirects = false;
      request.headers['Accept'] = 'image/jpeg,image/png,image/webp';
      request.headers['Authorization'] = 'Bearer $token';
      final response = await _client
          .send(request)
          .then(http.Response.fromStream)
          .timeout(_timeout);
      if (response.statusCode < 200 || response.statusCode >= 300) {
        Map<String, dynamic> decoded = {};
        try {
          decoded = _decodeObject(response.body);
        } on FormatException {
          // A failed binary response may not contain JSON.
        }
        throw _httpException(response.statusCode, _safeServerMessage(decoded));
      }
      return response.bodyBytes;
    } on ApiException {
      rethrow;
    } on TimeoutException {
      throw const ApiException(
        'The request timed out. Check your connection and try again.',
        type: ApiErrorType.networkError,
      );
    } on http.ClientException {
      throw ApiException(
        connectionFailureMessage(),
        type: ApiErrorType.networkError,
      );
    } catch (_) {
      throw ApiException(
        connectionFailureMessage(),
        type: ApiErrorType.networkError,
      );
    }
  }

  Future<Map<String, dynamic>> request(
    String path, {
    String? token,
    Map<String, dynamic>? body,
    bool post = false,
  }) async {
    final stopwatch = Stopwatch()..start();
    int? status;
    ApiErrorType? category;
    try {
      final request = http.Request(
        post ? 'POST' : 'GET',
        ApiConfig.validatedEndpoint(_baseUrl, path),
      )..followRedirects = false;
      request.headers['Accept'] = 'application/json';
      if (post) {
        request.headers['Content-Type'] = 'application/json';
        request.body = jsonEncode(body ?? <String, dynamic>{});
      }
      if (token != null) request.headers['Authorization'] = 'Bearer $token';

      final response = await _client
          .send(request)
          .then(http.Response.fromStream)
          .timeout(_timeout);
      status = response.statusCode;
      Map<String, dynamic> decoded;
      try {
        decoded = _decodeObject(response.body);
      } on FormatException {
        if (status >= 200 && status < 300) rethrow;
        decoded = <String, dynamic>{};
      }

      if (status < 200 || status >= 300) {
        final safeMessage = _safeServerMessage(decoded);
        throw _httpException(status, safeMessage);
      }
      if (decoded['success'] != true ||
          decoded['data'] is! Map<String, dynamic>) {
        throw const FormatException('Invalid response envelope.');
      }
      return decoded['data'] as Map<String, dynamic>;
    } on ApiConfigurationException catch (error) {
      category = ApiErrorType.configurationError;
      throw ApiException(error.message, type: ApiErrorType.configurationError);
    } on ApiException catch (error) {
      category = error.type;
      rethrow;
    } on TimeoutException {
      category = ApiErrorType.networkError;
      throw const ApiException(
        'The request timed out. Check your connection and try again.',
        type: ApiErrorType.networkError,
      );
    } on FormatException {
      category = ApiErrorType.serverError;
      throw const ApiException(
        'The server returned an unreadable response. Please try again.',
        type: ApiErrorType.serverError,
      );
    } on http.ClientException {
      category = ApiErrorType.networkError;
      throw ApiException(
        connectionFailureMessage(),
        type: ApiErrorType.networkError,
      );
    } catch (_) {
      category = ApiErrorType.networkError;
      throw ApiException(
        connectionFailureMessage(),
        type: ApiErrorType.networkError,
      );
    } finally {
      if (kDebugMode) {
        // Never log request headers, bodies, passwords, or bearer tokens.
        debugPrint(
          'API ${post ? 'POST' : 'GET'} $path status=${status ?? '-'} '
          'duration=${stopwatch.elapsedMilliseconds}ms '
          'category=${category?.code ?? 'OK'}',
        );
      }
    }
  }

  static Map<String, dynamic> _decodeObject(String body) {
    final decoded = jsonDecode(body);
    if (decoded is! Map<String, dynamic>) throw const FormatException();
    return decoded;
  }

  static String? _safeServerMessage(Map<String, dynamic> response) {
    final message = response['message'];
    if (message is! String || message.trim().isEmpty || message.length > 240) {
      return null;
    }
    final safe = message.trim();
    final normalized = safe.toLowerCase();
    const privateMarkers = [
      'sqlstate',
      'stack trace',
      'fatal error',
      'php warning',
      'socketexception',
      'formatexception',
      '<html',
      '<!doctype',
      'bearer ',
      'authorization:',
      'c:\\',
      '/var/www/',
    ];
    if (privateMarkers.any(normalized.contains)) return null;
    return safe;
  }

  static ApiException _httpException(int status, String? message) {
    if (status == 401) {
      return ApiException.unauthorized(
        message ?? 'Your session has expired. Please sign in again.',
      );
    }
    if (status == 403) {
      return ApiException(
        message ?? 'You do not have permission to perform this action.',
        type: ApiErrorType.forbidden,
        status: status,
      );
    }
    if (status == 404) {
      return ApiException(
        message ?? 'The requested information is not available.',
        type: ApiErrorType.notFound,
        status: status,
      );
    }
    if ({400, 409, 413, 415, 422}.contains(status)) {
      return ApiException(
        message ?? 'Please check the information and try again.',
        type: ApiErrorType.validationError,
        status: status,
      );
    }
    if (status == 429) {
      return ApiException(
        message ?? 'Too many requests. Please try again later.',
        type: ApiErrorType.rateLimited,
        status: status,
      );
    }
    if (status == 502 || status == 503 || status == 504) {
      return ApiException(
        'The AQUASENSE+ service is temporarily unavailable. Please try again.',
        type: ApiErrorType.serverUnavailable,
        status: status,
      );
    }
    return ApiException(
      status >= 500
          ? 'The server could not complete the request. Please try again.'
          : (message ?? 'The request could not be completed.'),
      type: ApiErrorType.serverError,
      status: status,
    );
  }
}
