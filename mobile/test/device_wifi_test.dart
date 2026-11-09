import 'dart:async';
import 'package:aquasense_mobile/screens/settings/device_wifi_screen.dart';
import 'package:aquasense_mobile/services/device_wifi_service.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';

class _WifiRepository implements DeviceWifiRepository {
  @override
  bool supported = true;
  List<WifiSetupDevice> devices = [
    const WifiSetupDevice(id: 'device-1', name: 'AQUASENSE_AQS-003_TEST'),
  ];
  Completer<void>? confirmation;
  DeviceWifiException? failure;
  String? receivedSsid;
  String? receivedPassword;
  int cancellations = 0;
  @override
  Future<List<WifiSetupDevice>> scan() async => devices;
  @override
  Future<void> provision({
    required String deviceId,
    required String setupCode,
    required String ssid,
    required String password,
  }) async {
    receivedSsid = ssid;
    receivedPassword = password;
    if (failure != null) throw failure!;
    await confirmation?.future;
  }

  @override
  Future<void> cancel() async {
    cancellations++;
  }
}

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  test(
    'Wi-Fi input validation uses UTF-8 byte limits and preserves valid spaces',
    () {
      expect(wifiNameError(''), isNotNull);
      expect(wifiNameError('é' * 17), isNotNull);
      expect(wifiNameError('é' * 16), isNull);
      expect(wifiNameError(' Cafe WiFi '), isNull);
      expect(wifiPasswordError('short'), isNotNull);
      expect(wifiPasswordError(' password '), isNull);
      expect(wifiPasswordError('a' * 64), isNull);
      expect(wifiPasswordError('g' * 64), isNotNull);
      expect(wifiPasswordError(''), isNull);
      expect(setupCodeError('1234'), isNotNull);
      expect(setupCodeError('0123456789abcdef'), isNull);
    },
  );

  test(
    'local provisioning channel forwards exact Wi-Fi input without cloud API',
    () async {
      const channel = MethodChannel('test/device_wifi');
      final calls = <MethodCall>[];
      TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
          .setMockMethodCallHandler(channel, (call) async {
            calls.add(call);
            if (call.method == 'scan') {
              return [
                {'id': 'dev', 'name': 'AQUASENSE_test'},
              ];
            }
            return null;
          });
      addTearDown(
        () => TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
            .setMockMethodCallHandler(channel, null),
      );
      const service = MobileDeviceWifiService(channel: channel);
      expect((await service.scan()).single.id, 'dev');
      await service.provision(
        deviceId: 'dev',
        setupCode: '0123456789ABCDEF',
        ssid: ' Cafe WiFi ',
        password: ' password ',
      );
      expect(calls.last.method, 'provision');
      expect(calls.last.arguments, {
        'deviceId': 'dev',
        'setupCode': '0123456789abcdef',
        'ssid': ' Cafe WiFi ',
        'password': ' password ',
      });
      final before = calls.length;
      await expectLater(
        service.provision(
          deviceId: 'dev',
          setupCode: 'bad',
          ssid: 'Cafe',
          password: 'password',
        ),
        throwsA(isA<DeviceWifiException>()),
      );
      expect(calls.length, before);
      await service.cancel();
      expect(calls.last.method, 'cancel');
    },
  );

  testWidgets('success waits for device confirmation and clears password', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(900, 1600);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    final repository = _WifiRepository()..confirmation = Completer<void>();
    await tester.pumpWidget(
      MaterialApp(home: DeviceWifiScreen(repository: repository)),
    );
    await tester.tap(find.text('Find my device'));
    await tester.pumpAndSettle();
    await tester.enterText(
      find.byKey(const Key('device-setup-code')),
      '0123456789abcdef',
    );
    await tester.enterText(
      find.byKey(const Key('device-wifi-name')),
      ' Cafe WiFi ',
    );
    await tester.enterText(
      find.byKey(const Key('device-wifi-password')),
      ' password ',
    );
    final field = tester.widget<EditableText>(
      find.descendant(
        of: find.byKey(const Key('device-wifi-password')),
        matching: find.byType(EditableText),
      ),
    );
    expect(field.obscureText, isTrue);
    await tester.ensureVisible(find.byKey(const Key('save-device-wifi')));
    await tester.tap(find.byKey(const Key('save-device-wifi')));
    await tester.pump();
    expect(repository.receivedSsid, ' Cafe WiFi ');
    expect(repository.receivedPassword, ' password ');
    expect(find.byKey(const Key('device-wifi-success')), findsNothing);
    repository.confirmation!.complete();
    await tester.pumpAndSettle();
    expect(find.byKey(const Key('device-wifi-success')), findsOneWidget);
    expect(field.controller.text, isEmpty);
    await tester.pumpWidget(const SizedBox());
    expect(repository.cancellations, 1);
  });

  testWidgets('no nearby device gives actionable setup instructions', (
    tester,
  ) async {
    final repository = _WifiRepository()..devices = [];
    await tester.pumpWidget(
      MaterialApp(home: DeviceWifiScreen(repository: repository)),
    );
    await tester.tap(find.text('Find my device'));
    await tester.pumpAndSettle();
    expect(find.textContaining('No AQUASENSE+ devices found.'), findsOneWidget);
    expect(find.byKey(const Key('device-wifi-success')), findsNothing);
    await tester.pumpWidget(const SizedBox());
  });

  testWidgets('unsupported targets explain the Android requirement', (
    tester,
  ) async {
    final repository = _WifiRepository()..supported = false;
    await tester.pumpWidget(
      MaterialApp(home: DeviceWifiScreen(repository: repository)),
    );
    expect(
      find.text('Device Wi-Fi setup is available in the Android app.'),
      findsOneWidget,
    );
    expect(find.byKey(const Key('device-wifi-password')), findsNothing);
    await tester.pumpWidget(const SizedBox());
  });
}
