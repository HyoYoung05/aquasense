import 'package:aquasense_mobile/services/notification_permission_service.dart';
import 'package:aquasense_mobile/screens/settings/settings_screen.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

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
    final permissions = _FakePermissionManager();
    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(body: SettingsScreen(permissionManager: permissions)),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('App permissions'), findsOneWidget);
    expect(find.text('Notifications'), findsOneWidget);
    expect(find.text('Display over other apps'), findsOneWidget);
    expect(find.text('Enable notifications'), findsOneWidget);
    expect(find.text('Allow display over other apps'), findsOneWidget);

    await tester.tap(find.text('Enable notifications'));
    await tester.pumpAndSettle();
    expect(permissions.notificationRequests, 1);
    expect(find.text('Enabled'), findsOneWidget);

    await tester.tap(find.text('Allow display over other apps'));
    await tester.pumpAndSettle();
    expect(permissions.overlayRequests, 1);
    expect(permissions.settingsRequests, 0);
  });
}
