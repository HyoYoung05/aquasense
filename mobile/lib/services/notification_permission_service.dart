import 'package:flutter/foundation.dart';
import 'package:permission_handler/permission_handler.dart';

enum NotificationPermissionOutcome {
  granted,
  denied,
  permanentlyDenied,
  notRequired,
  unavailable,
}

enum AppPermissionState { enabled, disabled, blocked, notAvailable }

abstract interface class NotificationPermissionRequester {
  Future<NotificationPermissionOutcome> requestAfterSignIn();
}

abstract interface class AppPermissionManager {
  Future<AppPermissionState> notificationStatus();
  Future<AppPermissionState> overlayStatus();
  Future<AppPermissionState> requestNotificationPermission();
  Future<AppPermissionState> requestOverlayPermission();
  Future<bool> openApplicationSettings();
}

class MobileNotificationPermissionService
    implements NotificationPermissionRequester, AppPermissionManager {
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

  bool get _isSupportedAndroid =>
      !kIsWeb && defaultTargetPlatform == TargetPlatform.android;

  AppPermissionState _state(PermissionStatus status) {
    if (status.isGranted) return AppPermissionState.enabled;
    if (status.isPermanentlyDenied || status.isRestricted) {
      return AppPermissionState.blocked;
    }
    return AppPermissionState.disabled;
  }

  @override
  Future<AppPermissionState> notificationStatus() async {
    if (!_isSupportedAndroid) return AppPermissionState.notAvailable;
    try {
      return _state(await Permission.notification.status);
    } catch (error) {
      debugPrint(
        'AQUASENSE+ notification status unavailable: ${error.runtimeType}',
      );
      return AppPermissionState.notAvailable;
    }
  }

  @override
  Future<AppPermissionState> overlayStatus() async {
    if (!_isSupportedAndroid) return AppPermissionState.notAvailable;
    try {
      return _state(await Permission.systemAlertWindow.status);
    } catch (error) {
      debugPrint('AQUASENSE+ overlay status unavailable: ${error.runtimeType}');
      return AppPermissionState.notAvailable;
    }
  }

  @override
  Future<AppPermissionState> requestNotificationPermission() async {
    if (!_isSupportedAndroid) return AppPermissionState.notAvailable;
    try {
      final current = await Permission.notification.status;
      if (current.isPermanentlyDenied || current.isRestricted) {
        await openAppSettings();
        return _state(await Permission.notification.status);
      }
      return _state(await Permission.notification.request());
    } catch (error) {
      debugPrint(
        'AQUASENSE+ notification request unavailable: ${error.runtimeType}',
      );
      return AppPermissionState.notAvailable;
    }
  }

  @override
  Future<AppPermissionState> requestOverlayPermission() async {
    if (!_isSupportedAndroid) return AppPermissionState.notAvailable;
    try {
      // Android handles SYSTEM_ALERT_WINDOW through its dedicated system page.
      await Permission.systemAlertWindow.request();
      return _state(await Permission.systemAlertWindow.status);
    } catch (error) {
      debugPrint(
        'AQUASENSE+ overlay request unavailable: ${error.runtimeType}',
      );
      return AppPermissionState.notAvailable;
    }
  }

  @override
  Future<bool> openApplicationSettings() async {
    if (!_isSupportedAndroid) return false;
    try {
      return await openAppSettings();
    } catch (error) {
      debugPrint('AQUASENSE+ app settings unavailable: ${error.runtimeType}');
      return false;
    }
  }
}
