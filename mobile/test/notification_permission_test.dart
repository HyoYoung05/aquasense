import 'dart:convert';

import 'package:aquasense_mobile/services/notification_permission_service.dart';
import 'package:aquasense_mobile/screens/settings/settings_screen.dart';
import 'package:aquasense_mobile/services/api_service.dart';
import 'package:aquasense_mobile/services/auth_service.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';

class _Store implements TokenStore {
  String? token = 'a' * 64;

  @override
  Future<void> clear() async => token = null;

  @override
  Future<String?> read() async => token;

  @override
  Future<void> write(String value) async => token = value;
}

AuthService _auth(http.Response Function(http.Request request) handler) =>
    AuthService(
      ApiService(
        baseUrl: 'https://api.example.test/api/mobile',
        client: MockClient((request) async => handler(request)),
      ),
      _Store(),
    );

http.Response _success(Map<String, dynamic> data) => http.Response(
  jsonEncode({'success': true, 'data': data}),
  200,
  headers: {'content-type': 'application/json'},
);

void _useTallView(WidgetTester tester) {
  tester.view.physicalSize = const Size(1000, 1600);
  tester.view.devicePixelRatio = 1;
  addTearDown(tester.view.resetPhysicalSize);
  addTearDown(tester.view.resetDevicePixelRatio);
}

class _FakePermissionManager implements AppPermissionManager {
  AppPermissionState notifications = AppPermissionState.disabled;
  AppPermissionState overlay = AppPermissionState.blocked;
  int notificationRequests = 0;
  int overlayRequests = 0;
  int settingsRequests = 0;

  @override
  Future<AppPermissionState> notificationStatus() async => notifications;

  @override
  Future<AppPermissionState> overlayStatus() async => overlay;

  @override
  Future<bool> openApplicationSettings() async {
    settingsRequests++;
    return true;
  }

  @override
  Future<AppPermissionState> requestNotificationPermission() async {
    notificationRequests++;
    notifications = AppPermissionState.enabled;
    return notifications;
  }

  @override
  Future<AppPermissionState> requestOverlayPermission() async {
    overlayRequests++;
    overlay = AppPermissionState.enabled;
    return overlay;
  }
}

void main() {
  test(
    'notification permission is not requested on unsupported platforms',
    () async {
      debugDefaultTargetPlatformOverride = TargetPlatform.windows;
      addTearDown(() => debugDefaultTargetPlatformOverride = null);

      const service = MobileNotificationPermissionService();
      expect(
        await service.requestAfterSignIn(),
        NotificationPermissionOutcome.notRequired,
      );
    },
  );

  testWidgets('settings exposes notification and Android overlay controls', (
    tester,
  ) async {
    _useTallView(tester);
    final permissions = _FakePermissionManager();
    final auth = _auth(
      (_) => _success({
        'user': {
          'id': 24,
          'full_name': 'Taylor Cruz',
          'email': 'owner@aquasense.test',
        },
      }),
    );
    await auth.restore();
    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: SettingsScreen(auth: auth, permissionManager: permissions),
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Settings'), findsOneWidget);
    expect(find.text('Account'), findsOneWidget);
    expect(find.text('Email address'), findsOneWidget);
    expect(find.text('owner@aquasense.test'), findsOneWidget);
    expect(find.text('Security'), findsOneWidget);
    expect(find.text('Password'), findsOneWidget);
    expect(find.text('App permissions'), findsOneWidget);
    expect(find.text('Notifications'), findsOneWidget);
    expect(find.text('Display over other apps'), findsOneWidget);
    expect(find.text('Enable notifications'), findsOneWidget);
    expect(find.text('Allow display over other apps'), findsOneWidget);

    await tester.ensureVisible(find.text('Enable notifications'));
    await tester.tap(find.text('Enable notifications'));
    await tester.pumpAndSettle();
    expect(permissions.notificationRequests, 1);
    expect(find.text('Enabled'), findsOneWidget);

    await tester.ensureVisible(find.text('Allow display over other apps'));
    await tester.tap(find.text('Allow display over other apps'));
    await tester.pumpAndSettle();
    expect(permissions.overlayRequests, 1);
    expect(permissions.settingsRequests, 0);
  });

  testWidgets('owner can change the account email from Settings', (
    tester,
  ) async {
    _useTallView(tester);
    final auth = _auth((request) {
      if (request.url.path.endsWith('profile.php')) {
        return _success({
          'user': {
            'id': 24,
            'full_name': 'Taylor Cruz',
            'email': 'owner@aquasense.test',
          },
        });
      }
      expect(request.url.path, endsWith('account-email.php'));
      final body = jsonDecode(request.body) as Map<String, dynamic>;
      expect(body['email'], 'new-owner@example.test');
      expect(body['current_password'], 'Temporary!2026');
      return _success({
        'user': {
          'id': 24,
          'full_name': 'Taylor Cruz',
          'email': 'new-owner@example.test',
        },
      });
    });
    await auth.restore();
    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: SettingsScreen(
            auth: auth,
            permissionManager: _FakePermissionManager(),
          ),
        ),
      ),
    );
    await tester.pumpAndSettle();
    await tester.ensureVisible(find.text('Change email'));
    await tester.tap(find.text('Change email'));
    await tester.pumpAndSettle();
    await tester.enterText(
      find.byKey(const ValueKey('settings-email')),
      'new-owner@example.test',
    );
    await tester.enterText(
      find.byKey(const ValueKey('settings-email-current-password')),
      'Temporary!2026',
    );
    await tester.tap(find.text('Update email'));
    await tester.pumpAndSettle();
    expect(auth.user?.email, 'new-owner@example.test');
    expect(find.text('new-owner@example.test'), findsOneWidget);
    expect(find.text('Email address updated.'), findsOneWidget);
  });

  testWidgets('password change clears the owner session', (tester) async {
    _useTallView(tester);
    final store = _Store();
    final auth = AuthService(
      ApiService(
        baseUrl: 'https://api.example.test/api/mobile',
        client: MockClient((request) async {
          if (request.url.path.endsWith('profile.php')) {
            return _success({
              'user': {
                'id': 24,
                'full_name': 'Taylor Cruz',
                'email': 'owner@aquasense.test',
              },
            });
          }
          expect(request.url.path, endsWith('account-password.php'));
          return _success({'message': 'Password updated. Sign in again.'});
        }),
      ),
      store,
    );
    await auth.restore();
    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: SettingsScreen(
            auth: auth,
            permissionManager: _FakePermissionManager(),
          ),
        ),
      ),
    );
    await tester.pumpAndSettle();
    await tester.ensureVisible(find.text('Change password'));
    await tester.tap(find.text('Change password'));
    await tester.pumpAndSettle();
    await tester.enterText(
      find.byKey(const ValueKey('settings-password-current')),
      'Temporary!2026',
    );
    await tester.enterText(
      find.byKey(const ValueKey('settings-password-new')),
      'NewPrivate!2026',
    );
    await tester.enterText(
      find.byKey(const ValueKey('settings-password-confirmation')),
      'NewPrivate!2026',
    );
    await tester.tap(find.text('Update password'));
    await tester.pumpAndSettle();
    expect(auth.state, AuthState.signedOut);
    expect(store.token, isNull);
    expect(auth.notice, contains('Sign in again'));
  });
}
