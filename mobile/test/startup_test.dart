import 'package:aquasense_mobile/app.dart';
import 'package:aquasense_mobile/main.dart' as startup;
import 'package:aquasense_mobile/screens/login/login_screen.dart';
import 'package:aquasense_mobile/services/auth_service.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';

class StartupTokenStore implements TokenStore {
  StartupTokenStore([this.token]);
  String? token;

  @override
  Future<String?> read() async => token;

  @override
  Future<void> write(String value) async => token = value;

  @override
  Future<void> clear() async => token = null;
}

void main() {
  testWidgets('valid HTTPS configuration initializes the normal login app', (
    tester,
  ) async {
    final app = startup.buildApplication(
      apiBaseUrl: 'https://api.example.test/api/mobile',
      releaseMode: true,
      client: MockClient((_) async => http.Response('{}', 500)),
      tokenStore: StartupTokenStore(),
    );

    expect(app, isA<OwnerApp>());
    await tester.pumpWidget(app);
    await tester.pumpAndSettle();
    expect(find.byType(LoginScreen), findsOneWidget);
    expect(find.byType(ConfigurationErrorApp), findsNothing);
    expect(tester.takeException(), isNull);
  });

  testWidgets('missing API URL paints the configuration error app', (
    tester,
  ) async {
    tester.platformDispatcher.platformBrightnessTestValue = Brightness.dark;
    addTearDown(tester.platformDispatcher.clearPlatformBrightnessTestValue);
    await tester.pumpWidget(
      startup.buildApplication(apiBaseUrl: '', releaseMode: true),
    );

    expect(find.byType(ConfigurationErrorApp), findsOneWidget);
    expect(find.text('AQUASENSE+'), findsOneWidget);
    expect(find.text('Server configuration error'), findsOneWidget);
    expect(find.textContaining('No API server is configured'), findsOneWidget);
    expect(find.text('Retry'), findsNothing);
    expect(find.textContaining('database password'), findsNothing);
    expect(tester.takeException(), isNull);
  });

  testWidgets('invalid API URL paints the configuration error app', (
    tester,
  ) async {
    await tester.pumpWidget(
      startup.buildApplication(
        apiBaseUrl: 'not-a-server-address',
        releaseMode: false,
      ),
    );

    expect(find.byType(ConfigurationErrorApp), findsOneWidget);
    expect(find.textContaining('server address is invalid'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('unreachable valid server uses normal unavailable state', (
    tester,
  ) async {
    final app = startup.buildApplication(
      apiBaseUrl: 'https://api.example.test/api/mobile',
      releaseMode: true,
      client: MockClient((_) async => throw http.ClientException('offline')),
      tokenStore: StartupTokenStore('a' * 64),
    );
    await tester.pumpWidget(app);
    await tester.pumpAndSettle();

    expect(find.byType(ConfigurationErrorApp), findsNothing);
    expect(find.byIcon(Icons.cloud_off_outlined), findsOneWidget);
    expect(find.text('Retry'), findsOneWidget);
    expect(find.textContaining('Unable to connect'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });
}
