import 'package:flutter/foundation.dart';

class ApiConfigurationException implements Exception {
  final String message;
  const ApiConfigurationException(this.message);
}

class ApiConfig {
  // Required at build time; one value configures every API request.
  static const configuredBaseUrl = String.fromEnvironment('API_BASE_URL');
  static String get baseUrl => validatedBaseUrl(configuredBaseUrl);

  static String resolveBaseUrl({
    String override = '',
    bool releaseMode = kReleaseMode,
  }) {
    return validatedBaseUrl(
      override.isNotEmpty ? override : configuredBaseUrl,
      releaseMode: releaseMode,
    );
  }

  static const timeout = Duration(seconds: 15);
  static Uri endpoint(String path) => validatedEndpoint(baseUrl, path);

  static String validatedBaseUrl(
    String address, {
    bool releaseMode = kReleaseMode,
  }) {
    if (address.isEmpty) {
      throw const ApiConfigurationException(
        'No API server is configured. Rebuild the app with API_BASE_URL set to the deployed HTTPS API.',
      );
    }
    final base = Uri.tryParse(address);
    if (base == null ||
        !base.hasAuthority ||
        base.host.isEmpty ||
        !['http', 'https'].contains(base.scheme) ||
        base.userInfo.isNotEmpty ||
        base.hasQuery ||
        base.hasFragment) {
      throw const ApiConfigurationException(
        'The server address is invalid. Rebuild the app with a valid API_BASE_URL.',
      );
    }
    if (releaseMode && base.scheme != 'https') {
      throw const ApiConfigurationException(
        'This release app needs an HTTPS server address. Development HTTP is allowed only in debug builds.',
      );
    }
    return address.replaceFirst(RegExp(r'/+$'), '');
  }

  static Uri validatedEndpoint(
    String address,
    String path, {
    bool releaseMode = kReleaseMode,
  }) {
    final base = validatedBaseUrl(address, releaseMode: releaseMode);
    final endpoint = Uri.tryParse(path);
    if (endpoint == null ||
        path.trim().isEmpty ||
        endpoint.hasScheme ||
        endpoint.hasAuthority ||
        endpoint.userInfo.isNotEmpty ||
        endpoint.hasFragment ||
        endpoint.pathSegments.contains('..')) {
      throw const ApiConfigurationException(
        'The API endpoint path is invalid.',
      );
    }
    final root = Uri.parse('$base/');
    return root.resolveUri(endpoint);
  }
}
