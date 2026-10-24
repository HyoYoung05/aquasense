import 'package:flutter/foundation.dart';
import 'package:permission_handler/permission_handler.dart';

enum NotificationPermissionOutcome {
  granted,
  denied,
  permanentlyDenied,
  notRequired,
  unavailable,
}

abstract interface class NotificationPermissionRequester {
  Future<NotificationPermissionOutcome> requestAfterSignIn();
}

class MobileNotificationPermissionService
    implements NotificationPermissionRequester {
  const MobileNotificationPermissionService();

  @override
  Future<NotificationPermissionOutcome> requestAfterSignIn() async {
    if (kIsWeb || defaultTargetPlatform != TargetPlatform.android) {
      return NotificationPermissionOutcome.notRequired;
    }

    try {
      final current = await Permission.notification.status;
      if (current.isGranted) return NotificationPermissionOutcome.granted;
      if (current.isPermanentlyDenied || current.isRestricted) {
        return NotificationPermissionOutcome.permanentlyDenied;
      }

      final requested = await Permission.notification.request();
      if (requested.isGranted) return NotificationPermissionOutcome.granted;
      if (requested.isPermanentlyDenied || requested.isRestricted) {
        return NotificationPermissionOutcome.permanentlyDenied;
      }
      return NotificationPermissionOutcome.denied;
    } catch (error) {
      debugPrint(
        'AQUASENSE+ notification permission unavailable: ${error.runtimeType}',
      );
      return NotificationPermissionOutcome.unavailable;
    }
  }
}
