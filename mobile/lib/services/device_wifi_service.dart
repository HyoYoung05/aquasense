import 'dart:async';
import 'dart:convert';
import 'package:flutter/foundation.dart';
import 'package:flutter/services.dart';

class DeviceWifiException implements Exception {
  final String message;
  const DeviceWifiException(this.message);
  @override
  String toString() => message;
}

class WifiSetupDevice {
  final String id;
  final String name;
  const WifiSetupDevice({required this.id, required this.name});
}

abstract class DeviceWifiRepository {
  bool get supported;
  Future<List<WifiSetupDevice>> scan();
  Future<void> provision({
    required String deviceId,
    required String setupCode,
    required String ssid,
    required String password,
  });
  Future<void> cancel();
}

class MobileDeviceWifiService implements DeviceWifiRepository {
  final MethodChannel channel;
  const MobileDeviceWifiService({
    this.channel = const MethodChannel('aquasense/device_wifi'),
  });
  @override
  bool get supported =>
      !kIsWeb && defaultTargetPlatform == TargetPlatform.android;
  Future<T?> _invoke<T>(
    String method, [
    Map<String, Object?>? arguments,
  ]) async {
    try {
      // Local channel only: no API, bearer token, logging, or phone storage.
      return await channel
          .invokeMethod<T>(method, arguments)
          .timeout(const Duration(seconds: 100));
    } on PlatformException catch (error) {
      throw DeviceWifiException(
        error.message ?? 'Device setup failed. Try again.',
      );
    } on MissingPluginException {
      throw const DeviceWifiException(
        'Install the updated Android app to set up device Wi-Fi.',
      );
    } on TimeoutException {
      await cancel();
      throw const DeviceWifiException(
        'Device setup timed out. Check Bluetooth and try again.',
      );
    }
  }

  @override
  Future<List<WifiSetupDevice>> scan() async {
    final devices = await _invoke<List<dynamic>>('scan') ?? [];
    return devices
        .map(
          (value) => WifiSetupDevice(
            id: value['id'] as String,
            name: value['name'] as String,
          ),
        )
        .toList();
  }

  @override
  Future<void> provision({
    required String deviceId,
    required String setupCode,
    required String ssid,
    required String password,
  }) async {
    if (wifiNameError(ssid) != null ||
        wifiPasswordError(password) != null ||
        setupCodeError(setupCode) != null) {
      throw const DeviceWifiException(
        'Check the setup code and Wi-Fi details.',
      );
    }
    await _invoke<void>('provision', {
      'deviceId': deviceId,
      'setupCode': setupCode.trim().toLowerCase(),
      'ssid': ssid,
      'password': password,
    });
  }

  @override
  Future<void> cancel() async {
    try {
      await channel
          .invokeMethod<void>('cancel')
          .timeout(const Duration(seconds: 3));
    } catch (_) {
      /* Safe best-effort cancellation. */
    }
  }
}

String? wifiNameError(String? value) =>
    value == null || utf8.encode(value).isEmpty
    ? 'Enter the Wi-Fi name.'
    : utf8.encode(value).length > 32
    ? 'Wi-Fi names must be at most 32 bytes.'
    : null;
String? wifiPasswordError(String? value) {
  if (value == null || value.isEmpty) return null;
  final count = utf8.encode(value).length;
  if ((count >= 8 && count <= 63) ||
      RegExp(r'^[a-fA-F0-9]{64}$').hasMatch(value)) {
    return null;
  }
  return 'Use 8–63 bytes, or a 64-character hexadecimal Wi-Fi key.';
}

String? setupCodeError(String? value) =>
    RegExp(r'^[a-fA-F0-9]{16}$').hasMatch(value?.trim() ?? '')
    ? null
    : 'Enter the 16-character setup code supplied with your device.';
