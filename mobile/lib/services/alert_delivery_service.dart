import 'package:flutter/foundation.dart';
import 'package:flutter/services.dart';

/// Android-only delivery. Tokens stay in the native service's memory.
class AlertDeliveryService {
  static const channel = MethodChannel('aquasense/alert_delivery');
  static bool get supported =>
      !kIsWeb && defaultTargetPlatform == TargetPlatform.android;

  static Future<Map<String, dynamic>> _call(
    String method, [
    Map<String, Object?>? arguments,
  ]) async {
    if (!supported) return {'supported': false};
    try {
      final result = await channel.invokeMapMethod<String, dynamic>(
        method,
        arguments,
      );
      return result ?? {'supported': true};
    } on MissingPluginException {
      return {'supported': false};
    } on PlatformException catch (error) {
      return {
        'supported': true,
        'error': error.message ?? 'Alert delivery unavailable.',
      };
    }
  }

  static Future<Map<String, dynamic>> status() => _call('status');
  static Future<Map<String, dynamic>> sync(Map<String, Object>? session) =>
      _call('sync', {'session': session});
  static Future<Map<String, dynamic>> setEnabled(
    bool enabled,
    Map<String, Object>? session,
  ) => _call('setEnabled', {'enabled': enabled, 'session': session});

  static Future<String> test({required bool overlay}) async {
    if (!supported) return 'Available in the Android app only.';
    try {
      final shown = await channel.invokeMethod<bool>(
        overlay ? 'testOverlay' : 'testNotification',
      );
      return shown == true
          ? (overlay
                ? 'Test pop-up displayed.'
                : 'Test notification sent. Check your notification panel.')
          : 'Android could not display the test. Check your phone settings.';
    } on MissingPluginException {
      return 'Install the updated Android app to test alerts.';
    } on PlatformException catch (error) {
      return error.message ?? 'Unable to show the test.';
    }
  }
}
