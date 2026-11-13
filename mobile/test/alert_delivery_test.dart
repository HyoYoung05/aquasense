import 'package:aquasense_mobile/services/alert_delivery_service.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  final calls = <MethodCall>[];
  const session = {
    'ownerId': 24,
    'apiBaseUrl': 'https://example.test/api/mobile',
    'token': 'test-token',
  };
  setUp(() {
    debugDefaultTargetPlatformOverride = TargetPlatform.android;
    calls.clear();
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
        .setMockMethodCallHandler(AlertDeliveryService.channel, (call) async {
          calls.add(call);
          if (call.method.startsWith('test')) return true;
          return {'supported': true, 'enabled': true, 'running': true};
        });
  });
  tearDown(() {
    debugDefaultTargetPlatformOverride = null;
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
        .setMockMethodCallHandler(AlertDeliveryService.channel, null);
  });
  test(
    'sync forwards authenticated session; sign-out clears native session',
    () async {
      await AlertDeliveryService.sync(session);
      await AlertDeliveryService.sync(null);
      expect(calls[0].arguments, {'session': session});
      expect(calls[1].arguments, {'session': null});
    },
  );
  test(
    'monitoring switch forwards explicit stop and current session',
    () async {
      await AlertDeliveryService.setEnabled(false, session);
      expect(calls.single.method, 'setEnabled');
      expect(calls.single.arguments, {'enabled': false, 'session': session});
    },
  );
  test('both tests use native delivery without API mutation', () async {
    expect(
      await AlertDeliveryService.test(overlay: false),
      contains('notification sent'),
    );
    expect(
      await AlertDeliveryService.test(overlay: true),
      'Test pop-up displayed.',
    );
    expect(calls.map((c) => c.method), ['testNotification', 'testOverlay']);
  });
  test('permission errors are shown to owner', () async {
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
        .setMockMethodCallHandler(AlertDeliveryService.channel, (_) async {
          throw PlatformException(
            code: 'permission',
            message: 'Enable notifications first.',
          );
        });
    expect(
      await AlertDeliveryService.test(overlay: false),
      'Enable notifications first.',
    );
  });
  test('unsupported platforms never contact the native service', () async {
    debugDefaultTargetPlatformOverride = TargetPlatform.windows;
    expect(await AlertDeliveryService.sync(session), {'supported': false});
    expect(calls, isEmpty);
  });
  test('missing native plugin is safe during tests and startup', () async {
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
        .setMockMethodCallHandler(AlertDeliveryService.channel, null);
    expect(await AlertDeliveryService.sync(null), {'supported': false});
  });
}
