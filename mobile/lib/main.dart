import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'app.dart';
import 'config/api_config.dart';
import 'services/api_service.dart';
import 'services/auth_service.dart';

void main() {
  WidgetsFlutterBinding.ensureInitialized();
  runApp(buildApplication());
}

Widget buildApplication({
  String? apiBaseUrl,
  bool releaseMode = kReleaseMode,
  http.Client? client,
  TokenStore? tokenStore,
}) {
  ApiService? api;
  try {
    api = ApiService(
      client: client,
      baseUrl: apiBaseUrl,
      releaseMode: releaseMode,
    );
    final auth = AuthService(
      api,
      tokenStore ?? SecureTokenStore(serverBaseUrl: api.baseUrl),
    );
    return OwnerApp(auth: auth);
  } on ApiConfigurationException catch (error) {
    api?.close();
    if (api == null) client?.close();
    return ConfigurationErrorApp(message: error.message);
  } catch (error) {
    api?.close();
    if (api == null) client?.close();
    debugPrint('AQUASENSE+ startup failed: ${error.runtimeType}');
    return const ConfigurationErrorApp(
      message:
          'The application could not initialize safely. Rebuild or reinstall the application.',
    );
  }
}
