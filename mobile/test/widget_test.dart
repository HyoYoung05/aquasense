import 'dart:convert';
import 'package:aquasense_mobile/app.dart';
import 'package:aquasense_mobile/services/api_service.dart';
import 'package:aquasense_mobile/services/auth_service.dart';
import 'package:aquasense_mobile/screens/home/home_screen.dart';
import 'package:aquasense_mobile/screens/login/login_screen.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';

class MemoryStore implements TokenStore {
  String? token;
  bool failWrite = false;
  bool failClear = false;
  @override
  Future<String?> read() async => token;
  @override
  Future<void> write(String value) async {
    if (failWrite) throw StateError('storage unavailable');
    token = value;
  }

  @override
  Future<void> clear() async {
    if (failClear) throw StateError('storage unavailable');
    token = null;
  }
}

const owner = {
  'id': 1,
  'full_name': 'Taylor Cruz',
  'email': 'owner@aquasense.test',
};
const testApiBaseUrl = 'https://api.example.test/api/mobile';
Map<String, dynamic> dashboard({bool empty = false}) => {
  'user': owner,
  'generated_at': DateTime.now().toUtc().toIso8601String(),
  'freshness_seconds': 600,
  'alerts': <Object>[],
  'establishments': empty
      ? []
      : [
          {
            'id': 1,
            'business_name': 'Demo Kusina',
            'traps': [
              {
                'id': 1,
                'name': 'Kitchen grease trap',
                'status': 'LOW',
                'device_code': 'DEMO-001',
                'device_status': 'ONLINE',
                'is_stale': false,
                'reading': {
                  'waste_level_percent': 32,
                  'temperature_c': 30.4,
                  'is_simulated': true,
                  'recorded_at': DateTime.now().toUtc().toIso8601String(),
                },
              },
            ],
          },
        ],
};
http.Response ok(Map<String, dynamic> data) =>
    http.Response(jsonEncode({'success': true, 'data': data}), 200);

class FakeServer {
  bool denied = false, offline = false, empty = false;
  bool delayLogin = false;
  int logouts = 0, calls = 0, loginCalls = 0;
  Future<http.Response> handle(http.Request request) async {
    calls++;
    if (offline) throw http.ClientException('Network lost');
    if (request.url.path.endsWith('login.php')) {
      loginCalls++;
      if (delayLogin) {
        await Future<void>.delayed(const Duration(milliseconds: 100));
      }
      final body = jsonDecode(request.body) as Map;
      if (body['password'] != 'correct') return http.Response('{}', 401);
      return ok({'token': 'a' * 64, 'user': owner});
    }
    if (request.url.path.endsWith('logout.php')) {
      logouts++;
      return ok({'message': 'Signed out'});
    }
    if (denied || request.headers['Authorization'] != 'Bearer ${'a' * 64}') {
      return http.Response('{}', 401);
    }
    if (request.url.path.endsWith('profile.php')) return ok({'user': owner});
    if (request.url.path.endsWith('dashboard.php')) {
      return ok(dashboard(empty: empty));
    }
    if (request.url.path.endsWith('oil-surrenders.php')) {
      return ok({'surrenders': <Object>[]});
    }
    if (request.url.path.endsWith('incentives.php')) {
      return ok({'summary': <Object>[], 'transactions': <Object>[]});
    }
    return http.Response('{}', 404);
  }
}

void main() {
  late MemoryStore store;
  late FakeServer server;
  late AuthService auth;
  setUp(() {
    store = MemoryStore();
    server = FakeServer();
    auth = AuthService(
      ApiService(client: MockClient(server.handle), baseUrl: testApiBaseUrl),
      store,
    );
  });
  tearDown(() {
    auth.api.close();
    auth.dispose();
  });

  test(
    'failed storage deletion cannot restore the logged-out session',
    () async {
      await auth.login('owner@aquasense.test', 'correct');
      store.failClear = true;
      server.offline = true;
      await auth.logout();
      expect(auth.state, AuthState.unavailable);
      server.offline = false;
      await auth.restore();
      expect(auth.state, AuthState.unavailable);
      expect(auth.user, isNull);
      store.failClear = false;
      await auth.restore();
      expect(auth.state, AuthState.signedOut);
      expect(store.token, isNull);
    },
  );

  test(
    'login persists a token, restores session, logout removes protected access',
    () async {
      await auth.restore();
      expect(auth.state, AuthState.signedOut);
      await auth.login('owner@aquasense.test', 'correct');
      expect(store.token, 'a' * 64);
      expect(auth.user?.fullName, 'Taylor Cruz');
      await auth.restore();
      expect(auth.state, AuthState.signedIn);
      expect(await auth.logout(), isNull);
      expect(store.token, isNull);
      expect(auth.user, isNull);
      expect(auth.state, AuthState.signedOut);
      await expectLater(
        auth.get('dashboard.php'),
        throwsA(isA<ApiException>()),
      );
      expect(server.logouts, 1);
    },
  );
  test('invalid credentials do not save a session', () async {
    await expectLater(
      auth.login('owner@aquasense.test', 'wrong'),
      throwsA(isA<ApiException>()),
    );
    expect(store.token, isNull);
  });
  test('expired token is cleared on restore and protected request', () async {
    store.token = 'a' * 64;
    server.denied = true;
    await auth.restore();
    expect(auth.state, AuthState.signedOut);
    expect(store.token, isNull);
    expect(auth.notice, contains('expired'));
    server.denied = false;
    await auth.login('owner@aquasense.test', 'correct');
    server.denied = true;
    await expectLater(auth.get('dashboard.php'), throwsA(isA<ApiException>()));
    expect(auth.state, AuthState.signedOut);
  });
  test(
    'offline restore does not expose a protected screen or discard token',
    () async {
      store.token = 'a' * 64;
      server.offline = true;
      await auth.restore();
      expect(auth.state, AuthState.unavailable);
      expect(store.token, isNotNull);
      server.offline = false;
      await auth.restore();
      expect(auth.state, AuthState.signedIn);
    },
  );
  test(
    'offline logout clears local authentication and reports pending revocation',
    () async {
      await auth.login('owner@aquasense.test', 'correct');
      server.offline = true;
      expect(await auth.logout(), contains('could not be confirmed'));
      expect(store.token, isNull);
      expect(auth.state, AuthState.signedOut);
    },
  );
  test(
    'storage failure revokes issued token without opening the dashboard',
    () async {
      store.failWrite = true;
      await expectLater(
        auth.login('owner@aquasense.test', 'correct'),
        throwsA(isA<ApiException>()),
      );
      expect(server.logouts, 1);
      expect(auth.state, isNot(AuthState.signedIn));
    },
  );
  test('malformed server output never displays a raw server error', () async {
    final api = ApiService(
      baseUrl: testApiBaseUrl,
      client: MockClient(
        (_) async => http.Response('<html>SQLSTATE private detail</html>', 200),
      ),
    );
    await expectLater(
      api.request('dashboard.php'),
      throwsA(
        isA<ApiException>().having(
          (e) => e.message,
          'message',
          isNot(contains('SQLSTATE')),
        ),
      ),
    );
    api.close();
  });
  testWidgets(
    'form validation, show password, login, logout and back protection',
    (tester) async {
      await tester.pumpWidget(OwnerApp(auth: auth));
      await tester.pumpAndSettle();
      await tester.ensureVisible(find.text('Sign in'));
      await tester.tap(find.text('Sign in'));
      await tester.pumpAndSettle();
      expect(find.text('Enter your email address.'), findsOneWidget);
      await tester.enterText(
        find.byType(TextFormField).at(0),
        'owner@aquasense.test',
      );
      await tester.enterText(find.byType(TextFormField).at(1), 'correct');
      await tester.tap(find.byTooltip('Show password'));
      await tester.pump();
      expect(find.byTooltip('Hide password'), findsOneWidget);
      await tester.ensureVisible(find.text('Sign in'));
      await tester.tap(find.text('Sign in'));
      await tester.pumpAndSettle();
      expect(find.byType(HomeScreen), findsOneWidget);
      expect(find.text('Demo Kusina'), findsWidgets);
      expect(find.text('32%'), findsOneWidget);
      await tester.tap(find.byTooltip('Sign out'));
      await tester.pumpAndSettle();
      expect(find.byType(LoginScreen), findsOneWidget);
      expect(find.byType(HomeScreen), findsNothing);
      await tester.binding.handlePopRoute();
      await tester.pumpAndSettle();
      expect(find.byType(HomeScreen), findsNothing);
      expect(store.token, isNull);
    },
  );
  for (final size in [
    const Size(320, 568),
    const Size(390, 844),
    const Size(844, 390),
    const Size(1024, 768),
  ]) {
    testWidgets('login and dashboard fit ${size.width}x${size.height}', (
      tester,
    ) async {
      tester.view.physicalSize = size;
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);
      await tester.pumpWidget(OwnerApp(auth: auth));
      await tester.pumpAndSettle();
      expect(tester.takeException(), isNull);
      await auth.login('owner@aquasense.test', 'correct');
      await tester.pumpAndSettle();
      expect(tester.takeException(), isNull);
      await tester.pumpWidget(const SizedBox());
    });
  }
  testWidgets('large text and empty establishment state', (tester) async {
    server.empty = true;
    store.token = 'a' * 64;
    tester.platformDispatcher.textScaleFactorTestValue = 2;
    addTearDown(tester.platformDispatcher.clearTextScaleFactorTestValue);
    await tester.pumpWidget(OwnerApp(auth: auth));
    await tester.pumpAndSettle();
    expect(find.text('No registered establishment'), findsOneWidget);
    expect(tester.takeException(), isNull);
    await tester.pumpWidget(const SizedBox());
  });
  testWidgets('Phase 2 dashboard does not start background polling', (
    tester,
  ) async {
    store.token = 'a' * 64;
    await tester.pumpWidget(OwnerApp(auth: auth));
    await tester.pumpAndSettle();
    final calls = server.calls;
    await tester.pump(const Duration(minutes: 11));
    expect(find.text('32%'), findsOneWidget);
    expect(server.calls, calls);
    await tester.pumpWidget(const SizedBox());
  });

  testWidgets('login loading prevents repeated submission', (tester) async {
    server.delayLogin = true;
    await tester.pumpWidget(OwnerApp(auth: auth));
    await tester.pumpAndSettle();
    await tester.enterText(
      find.byType(TextFormField).at(0),
      'owner@aquasense.test',
    );
    await tester.enterText(find.byType(TextFormField).at(1), 'correct');
    await tester.ensureVisible(find.text('Sign in'));
    await tester.tap(find.text('Sign in'));
    await tester.pump();
    expect(find.text('Signing in…'), findsOneWidget);
    await tester.tap(find.text('Signing in…'));
    await tester.pump(const Duration(milliseconds: 150));
    await tester.pumpAndSettle();
    expect(server.loginCalls, 1);
    expect(find.byType(HomeScreen), findsOneWidget);
  });

  testWidgets('unavailable session can retry without losing its token', (
    tester,
  ) async {
    store.token = 'a' * 64;
    server.offline = true;
    await tester.pumpWidget(OwnerApp(auth: auth));
    await tester.pumpAndSettle();
    expect(find.text('Retry'), findsOneWidget);
    expect(store.token, isNotNull);
    server.offline = false;
    await tester.tap(find.text('Retry'));
    await tester.pumpAndSettle();
    expect(find.byType(HomeScreen), findsOneWidget);
    expect(store.token, isNotNull);
  });

  testWidgets('navigation shell exposes Oil Surrender, Alerts, and profile', (
    tester,
  ) async {
    await auth.login('owner@aquasense.test', 'correct');
    await tester.pumpWidget(OwnerApp(auth: auth));
    await tester.pumpAndSettle();
    await tester.tap(
      find.descendant(
        of: find.byType(NavigationRail),
        matching: find.byIcon(Icons.oil_barrel_outlined),
      ),
    );
    await tester.pumpAndSettle();
    expect(find.text('New Surrender'), findsOneWidget);
    expect(find.text('No oil surrender submissions yet.'), findsOneWidget);
    await tester.tap(
      find.descendant(
        of: find.byType(NavigationRail),
        matching: find.byIcon(Icons.notifications_outlined),
      ),
    );
    await tester.pumpAndSettle();
    expect(find.text('Unable to load alerts'), findsOneWidget);
    await tester.tap(
      find.descendant(
        of: find.byType(NavigationRail),
        matching: find.byIcon(Icons.person_outline),
      ),
    );
    await tester.pumpAndSettle();
    expect(find.text('owner@aquasense.test'), findsOneWidget);
    expect(find.textContaining('Owner App v0.6.0+12'), findsOneWidget);
  });
}
