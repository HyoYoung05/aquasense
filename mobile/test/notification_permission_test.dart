import 'package:aquasense_mobile/services/notification_permission_service.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_test/flutter_test.dart';

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
}
