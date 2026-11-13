import 'dart:convert';

import 'package:aquasense_mobile/models/monitoring.dart';
import 'package:aquasense_mobile/screens/monitoring/monitoring_screen.dart';
import 'package:aquasense_mobile/services/api_service.dart';
import 'package:aquasense_mobile/services/auth_service.dart';
import 'package:aquasense_mobile/services/monitoring_service.dart';
import 'package:aquasense_mobile/theme/app_theme.dart';
import 'package:fl_chart/fl_chart.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';

const _baseUrl = 'https://api.example.test/api/mobile';
final _at = DateTime.utc(2026, 10, 4, 6, 35);

class _Store implements TokenStore {
  String? token = 'a' * 64;
  @override
  Future<void> clear() async => token = null;
  @override
  Future<String?> read() async => token;
  @override
  Future<void> write(String value) async => token = value;
}

class _FakeRepository implements MonitoringRepository {
  MonitoringSnapshot current = _snapshot();
  TelemetryHistory Function(int trapId, HistoryRange range, int page) history =
      (trapId, range, page) =>
          _history(trapId: trapId, range: range, page: page);
  int currentCalls = 0;
  final List<(int, HistoryRange, int)> historyCalls = [];
  ApiException? currentError;
  ApiException? historyError;
  DateTime? customFrom;
  DateTime? customTo;

  @override
  Future<MonitoringSnapshot> loadCurrent() async {
    currentCalls++;
    if (currentError case final error?) throw error;
    return current;
  }

  @override
  Future<TelemetryHistory> loadHistory({
    required int greaseTrapId,
    required HistoryRange range,
    DateTime? from,
    DateTime? to,
    int page = 1,
  }) async {
    historyCalls.add((greaseTrapId, range, page));
    customFrom = from;
    customTo = to;
    if (historyError case final error?) throw error;
    return history(greaseTrapId, range, page);
  }
}

MonitoringReading _reading({
  double? waste = 58,
  double? gasValue = 725,
  double? gasRawAdc = 1450,
}) => MonitoringReading(
  wasteLevel: waste,
  ultrasonicDistance: 12.4,
  temperature: 31.5,
  turbidity: 410,
  turbidityRawAdc: 1820,
  flowRate: 1.8,
  flowPulseCount: 450,
  gasValue: gasValue,
  odorValue: 240,
  gasRawAdc: gasRawAdc,
  condition: 'MEDIUM',
  isSimulated: false,
  isTest: true,
  recordedAt: _at,
);

GreaseTrapMonitoring _trap({
  int id = 12,
  String name = 'Main Trap',
  String device = 'AQS-001',
  String status = 'ONLINE',
  bool stale = false,
  MonitoringReading? reading,
  GasSensorRuntime? gasRuntime,
}) => GreaseTrapMonitoring(
  establishmentId: 1,
  businessName: 'Demo Kusina',
  greaseTrapId: id,
  greaseTrapName: name,
  deviceCode: device,
  deviceName: 'Kitchen monitor',
  firmwareVersion: '1.0.0',
  deviceStatus: status,
  lastSeenAt: _at,
  sensorState: status == 'ONLINE' ? 'MEDIUM' : 'OFFLINE',
  isStale: stale,
  reading: reading ?? _reading(),
  gasRuntime: gasRuntime,
);

MonitoringSnapshot _snapshot() => MonitoringSnapshot(
  generatedAt: _at,
  freshness: const Duration(minutes: 10),
  traps: [
    _trap(),
    _trap(
      id: 13,
      name: 'Backup Trap',
      device: 'AQS-002',
      status: 'OFFLINE',
      stale: true,
      reading: _reading(waste: 0),
    ),
  ],
);

TelemetryPoint _point(
  int minute, {
  double? waste = 58,
  double? temperature = 31.5,
}) => TelemetryPoint(
  deviceCode: 'AQS-001',
  wasteLevel: waste,
  ultrasonicDistance: waste == null ? null : 12.4,
  temperature: temperature,
  turbidity: waste == null ? null : 410,
  turbidityRawAdc: waste == null ? null : 1820,
  flowRate: waste == null ? null : 1.8,
  flowPulseCount: waste == null ? null : 450,
  gasValue: waste == null ? null : 725,
  odorValue: waste == null ? null : 240,
  gasRawAdc: waste == null ? null : 1450,
  condition: 'MEDIUM',
  isSimulated: false,
  isTest: true,
  recordedAt: _at.subtract(Duration(minutes: minute)),
);

TelemetryHistory _history({
  int trapId = 12,
  HistoryRange range = HistoryRange.today,
  int page = 1,
  int pages = 1,
  List<TelemetryPoint>? readings,
}) => TelemetryHistory(
  trapId: trapId,
  trapName: trapId == 12 ? 'Main Trap' : 'Backup Trap',
  businessName: 'Demo Kusina',
  range: range,
  from: _at.subtract(const Duration(days: 1)),
  to: _at,
  page: page,
  pages: pages,
  total: readings?.length ?? 3,
  readings:
      readings ?? [_point(0), _point(10, waste: null), _point(20, waste: 42)],
);

AuthService _auth({http.Client? client, _Store? store}) {
  final api = ApiService(
    baseUrl: _baseUrl,
    client: client ?? MockClient((_) async => http.Response('{}', 500)),
  );
  final auth = AuthService(api, store ?? _Store());
  auth.state = AuthState.signedIn;
  return auth;
}

Widget _app(_FakeRepository repository, AuthService auth) => MaterialApp(
  home: Scaffold(
    body: MonitoringScreen(auth: auth, repository: repository),
  ),
);

void main() {
  for (final serverPageSize in [10, 50]) {
    test(
      'history service shows ten rows with $serverPageSize-row API pages',
      () async {
        final auth = _auth(
          client: MockClient((request) async {
            if (request.url.path.endsWith('profile.php')) {
              return http.Response(
                jsonEncode({
                  'success': true,
                  'data': {
                    'user': {
                      'id': 1,
                      'full_name': 'Test Owner',
                      'email': 'owner@example.test',
                    },
                  },
                }),
                200,
              );
            }
            expect(request.url.queryParameters['grease_trap_id'], '12');
            expect(request.url.queryParameters['page_size'], '10');
            final page = int.parse(request.url.queryParameters['page']!);
            final pages = (53 / serverPageSize).ceil();
            final actualPage = page.clamp(1, pages);
            final first = (actualPage - 1) * serverPageSize;
            return http.Response(
              jsonEncode({
                'success': true,
                'data': {
                  'trap': {
                    'id': 12,
                    'name': 'Main Trap',
                    'business_name': 'Demo Kusina',
                  },
                  'range': 'today',
                  'from': '2026-10-03T00:00:00Z',
                  'to': '2026-10-04T00:00:00Z',
                  'page': actualPage,
                  'pages': pages,
                  'total': 53,
                  if (serverPageSize == 10) 'page_size': 10,
                  'readings': List.generate(
                    (53 - first).clamp(0, serverPageSize),
                    (index) => {
                      'device_code': 'AQS-001',
                      'waste_level_percent': first + index,
                      'is_simulated': false,
                      'is_test': false,
                      'level_status': 'NORMAL',
                      'recorded_at': '2026-10-04T06:35:00Z',
                    },
                  ),
                },
              }),
              200,
            );
          }),
        );
        await auth.restore();
        final service = MonitoringService(auth);
        for (final page in [1, 2, 5, 6, 1]) {
          final history = await service.loadHistory(
            greaseTrapId: 12,
            range: HistoryRange.today,
            page: page,
          );
          expect(history.page, page);
          expect(history.pages, 6);
          expect(history.readings.length, page == 6 ? 3 : 10);
          expect(history.readings.first.wasteLevel, (page - 1) * 10);
          expect(
            history.readings.last.wasteLevel,
            page == 6 ? 52 : page * 10 - 1,
          );
        }
        auth.api.close();
        auth.dispose();
      },
    );
  }

  test(
    'monitoring models preserve valid zero, null sensors, and unknown fields',
    () {
      final parsed = MonitoringSnapshot.fromJson({
        'generated_at': '2026-10-04T06:35:00Z',
        'freshness_seconds': 600,
        'unknown': true,
        'traps': [
          {
            'establishment_id': 1,
            'business_name': 'Demo Kusina',
            'grease_trap_id': 12,
            'grease_trap_name': 'Main Trap',
            'device_code': 'AQS-001',
            'device_status': 'ONLINE',
            'last_seen_at': null,
            'sensor_state': 'NORMAL',
            'is_stale': false,
            'reading': {
              'waste_level_percent': 0,
              'ultrasonic_distance_cm': 0,
              'temperature_c': null,
              'turbidity_ntu': null,
              'turbidity_raw_adc': null,
              'flow_rate_lpm': null,
              'flow_pulse_count': null,
              'gas_value': null,
              'gas_raw_adc': null,
              'ambient_temperature_c': null,
              'ens160_aqi': null,
              'tvoc_ppb': null,
              'eco2_ppm': null,
              'condition': 'NORMAL',
              'is_simulated': false,
              'is_test': false,
              'recorded_at': '2026-10-04T06:35:00Z',
              'future_field': 'ignored',
            },
          },
        ],
      });
      expect(parsed.traps.single.reading!.wasteLevel, 0);
      expect(parsed.traps.single.reading!.ultrasonicDistance, 0);
      expect(parsed.traps.single.reading!.temperature, isNull);
      expect(parsed.traps.single.lastSeenAt, isNull);
    },
  );

  test(
    'history parses all ranges, sensor values, pages, and missing points',
    () {
      for (final range in HistoryRange.values) {
        final parsed = TelemetryHistory.fromJson({
          'trap': {
            'id': 12,
            'name': 'Main Trap',
            'business_name': 'Demo Kusina',
          },
          'range': range.apiValue,
          'from': '2026-10-03T00:00:00Z',
          'to': '2026-10-04T00:00:00Z',
          'page': 1,
          'pages': 2,
          'total': 51,
          'readings': [
            {
              'device_code': 'AQS-001',
              'waste_level_percent': null,
              'ultrasonic_distance_cm': 12.4,
              'temperature_c': 31.5,
              'turbidity_ntu': 410,
              'turbidity_raw_adc': 1820,
              'flow_rate_lpm': 1.8,
              'flow_pulse_count': 450,
              'gas_value': 725,
              'odor_value': 240,
              'gas_raw_adc': 1450,
              'ambient_temperature_c': 29.8,
              'ens160_aqi': 2,
              'tvoc_ppb': 120,
              'eco2_ppm': 510,
              'level_status': 'MEDIUM',
              'is_simulated': false,
              'is_test': true,
              'recorded_at': '2026-10-04T06:35:00Z',
            },
          ],
        });
        expect(parsed.range, range);
        expect(parsed.hasMore, isTrue);
        expect(parsed.readings.single.wasteLevel, isNull);
        expect(parsed.readings.single.gasValue, 725);
        expect(parsed.readings.single.odorValue, 240);
        expect(parsed.readings.single.turbidityRawAdc, 1820);
        expect(parsed.readings.single.gasRawAdc, 1450);
      }
    },
  );

  test(
    'custom history requires both dates before requesting the API',
    () async {
      final auth = _auth();
      final service = MonitoringService(auth);
      await expectLater(
        service.loadHistory(greaseTrapId: 12, range: HistoryRange.custom),
        throwsA(
          isA<ApiException>().having(
            (error) => error.type,
            'type',
            ApiErrorType.validationError,
          ),
        ),
      );
      auth.api.close();
      auth.dispose();
    },
  );

  test('monitoring 401 expires the centralized session', () async {
    final store = _Store();
    final auth = _auth(
      store: store,
      client: MockClient(
        (request) async => request.url.path.endsWith('login.php')
            ? http.Response(
                jsonEncode({
                  'success': true,
                  'data': {
                    'token': 'a' * 64,
                    'user': {
                      'id': 1,
                      'full_name': 'Test Owner',
                      'email': 'owner@example.test',
                    },
                  },
                }),
                200,
              )
            : http.Response(
                jsonEncode({'success': false, 'message': 'Expired'}),
                401,
              ),
      ),
    );
    await auth.login('owner@example.test', 'correct');
    await expectLater(
      MonitoringService(auth).loadCurrent(),
      throwsA(isA<ApiException>()),
    );
    expect(auth.state, AuthState.signedOut);
    expect(store.token, isNull);
    auth.api.close();
    auth.dispose();
  });

  test('temporary monitoring network error preserves the session', () async {
    final store = _Store();
    var offline = false;
    final auth = _auth(
      store: store,
      client: MockClient((request) async {
        if (offline) throw http.ClientException('offline');
        return http.Response(
          jsonEncode({
            'success': true,
            'data': {
              'user': {
                'id': 1,
                'full_name': 'Test Owner',
                'email': 'owner@example.test',
              },
            },
          }),
          200,
        );
      }),
    );
    await auth.restore();
    offline = true;
    await expectLater(
      MonitoringService(auth).loadCurrent(),
      throwsA(isA<ApiException>()),
    );
    expect(auth.state, AuthState.signedIn);
    expect(store.token, isNotNull);
    auth.api.close();
    auth.dispose();
  });

  testWidgets(
    'monitoring shows current values, device, status and all sensors',
    (tester) async {
      tester.view.physicalSize = const Size(900, 1500);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);
      final repository = _FakeRepository();
      final auth = _auth();
      await tester.pumpWidget(_app(repository, auth));
      await tester.pumpAndSettle();
      expect(find.text('Grease Trap Monitoring'), findsOneWidget);
      expect(find.text('58%'), findsWidgets);
      expect(find.text('12.4 cm'), findsWidgets);
      expect(find.text('31.5 °C'), findsWidgets);
      expect(find.text('410 NTU'), findsWidgets);
      expect(find.text('1.8 L/min'), findsWidgets);
      expect(find.text('725 calibrated'), findsWidgets);
      expect(find.text('240 raw'), findsNothing);
      expect(find.textContaining('Odor'), findsNothing);
      expect(
        SensorMetric.values.map((metric) => metric.label),
        isNot(contains('Odor Sensor')),
      );
      expect(find.text('29.8 °C'), findsNothing);
      final sensorPanel = find.byKey(const Key('sensor-summary-panel'));
      expect(sensorPanel, findsOneWidget);
      expect(
        find.descendant(
          of: sensorPanel,
          matching: find.text('Ambient Temperature'),
        ),
        findsNothing,
      );
      for (final removedLabel in [
        'Ambient Temperature',
        'ENS160 AQI',
        'ENS160 TVOC',
        'ENS160 eCO2',
        'Turbidity Raw',
        'Flow Pulses',
        'Air Quality',
        'Humidity',
        'MQ-135 Raw',
      ]) {
        expect(
          find.descendant(of: sensorPanel, matching: find.text(removedLabel)),
          findsNothing,
        );
      }
      for (final removedMetric in [
        'Ambient Temperature',
        'Humidity',
        'ENS160 AQI',
        'ENS160 TVOC',
        'ENS160 eCO2',
      ]) {
        expect(
          SensorMetric.values.map((metric) => metric.label),
          isNot(contains(removedMetric)),
        );
      }
      expect(find.text('AQS-001'), findsWidgets);
      expect(find.text('ONLINE'), findsWidgets);
      expect(find.textContaining('Oct 4, 2026'), findsWidgets);
      await tester.pumpWidget(const SizedBox());
      auth.api.close();
      auth.dispose();
    },
  );

  testWidgets('valid zero and backend offline stale state remain distinct', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(900, 1500);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    final repository = _FakeRepository();
    final auth = _auth();
    await tester.pumpWidget(_app(repository, auth));
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('grease-trap-selector')));
    await tester.pumpAndSettle();
    await tester.tap(find.textContaining('Backup Trap').last);
    await tester.pumpAndSettle();
    expect(find.text('0%'), findsWidgets);
    expect(find.text('OFFLINE'), findsWidgets);
    final statusFact = find.byKey(const ValueKey('monitoring-device-status'));
    expect(
      tester
          .widget<Text>(
            find.descendant(of: statusFact, matching: find.text('OFFLINE')),
          )
          .style
          ?.color,
      offlineStatus,
    );
    expect(find.textContaining('Device Offline'), findsOneWidget);
    await tester.pumpWidget(const SizedBox());
    auth.api.close();
    auth.dispose();
  });

  testWidgets('missing telemetry and optional sensors show honest states', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(900, 1500);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    final repository = _FakeRepository()
      ..current = MonitoringSnapshot(
        generatedAt: _at,
        freshness: const Duration(minutes: 10),
        traps: [
          GreaseTrapMonitoring(
            establishmentId: 1,
            businessName: 'Demo Kusina',
            greaseTrapId: 12,
            greaseTrapName: 'Empty Trap',
            deviceCode: 'AQS-003',
            deviceName: null,
            firmwareVersion: null,
            deviceStatus: 'OFFLINE',
            lastSeenAt: null,
            sensorState: 'AWAITING SENSOR DATA',
            isStale: false,
            reading: null,
          ),
        ],
      )
      ..history = (_, range, page) => _history(range: range, readings: []);
    final auth = _auth();
    await tester.pumpWidget(_app(repository, auth));
    await tester.pumpAndSettle();
    expect(find.text('Awaiting Sensor Data'), findsWidgets);
    expect(find.text('No data'), findsWidgets);
    expect(find.text('Not connected'), findsNWidgets(3));
    expect(
      find.descendant(
        of: find.byKey(const Key('sensor-summary-panel')),
        matching: find.text('Device offline'),
      ),
      findsOneWidget,
    );
    expect(find.text('Not calibrated'), findsNothing);
    await tester.scrollUntilVisible(
      find.text('No telemetry history'),
      300,
      scrollable: find.byType(Scrollable).first,
    );
    expect(find.text('No telemetry history'), findsOneWidget);
    await tester.pumpWidget(const SizedBox());
    auth.api.close();
    auth.dispose();
  });

  testWidgets(
    'startup waiting changes to real telemetry on automatic refresh',
    (tester) async {
      tester.view.physicalSize = const Size(900, 1500);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);
      final missing = _reading(gasValue: null, gasRawAdc: null);
      MonitoringSnapshot current(
        MonitoringReading reading, {
        bool stale = false,
      }) => MonitoringSnapshot(
        generatedAt: _at,
        freshness: const Duration(minutes: 10),
        traps: [_trap(reading: reading, stale: stale)],
      );
      final repository = _FakeRepository()..current = current(missing);
      final auth = _auth();
      await tester.pumpWidget(_app(repository, auth));
      await tester.pumpAndSettle();
      final panel = find.byKey(const Key('sensor-summary-panel'));
      Finder panelText(String text) =>
          find.descendant(of: panel, matching: find.text(text));
      expect(panelText('Sensor warming up'), findsOneWidget);
      expect(find.textContaining('Gas warm-up takes 3 minutes'), findsNothing);
      expect(panelText('Not available'), findsNothing);
      expect(panelText('Not connected'), findsNothing);

      await tester.pump(const Duration(seconds: 30));
      await tester.pumpAndSettle();
      expect(panelText('Sensor warming up'), findsOneWidget);
      expect(panelText('Please check wiring of sensor'), findsNothing);
      // Refreshes must not restart the warm-up or fabricate readings on expiry.
      await tester.pump(const Duration(seconds: 30));
      await tester.pumpAndSettle();
      expect(panelText('Please check wiring of sensor'), findsOneWidget);

      repository.current = current(missing, stale: true);
      await tester.pump(MonitoringScreen.refreshInterval);
      await tester.pumpAndSettle();
      expect(panelText('Please check wiring of sensor'), findsOneWidget);
      expect(find.textContaining('Gas warm-up takes 3 minutes'), findsNothing);

      repository.current = current(_reading(gasValue: null, gasRawAdc: 1200));
      await tester.pump(MonitoringScreen.refreshInterval);
      await tester.pumpAndSettle();
      expect(panelText('1200 ADC (raw)'), findsOneWidget);
      expect(panelText('Sensor warming up'), findsNothing);
      expect(panelText('Please check wiring of sensor'), findsNothing);
      expect(find.textContaining('Gas warm-up takes 3 minutes'), findsNothing);

      repository.current = current(_reading());
      await tester.pump(MonitoringScreen.refreshInterval);
      await tester.pumpAndSettle();
      expect(panelText('725 calibrated'), findsOneWidget);
      repository.current = current(missing);
      await tester.pump(MonitoringScreen.refreshInterval);
      await tester.pumpAndSettle();
      expect(panelText('Sensor warming up'), findsOneWidget);
      expect(panelText('Please check wiring of sensor'), findsNothing);
      await tester.pumpWidget(const SizedBox());
      auth.api.close();
      auth.dispose();
    },
  );

  testWidgets(
    'Gas reset hides cached telemetry and restarts its one-minute warm-up',
    (tester) async {
      tester.view.physicalSize = const Size(900, 1500);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);
      final repository = _FakeRepository();
      MonitoringSnapshot current(
        String boot,
        String state, {
        double? raw,
        int second = 0,
        String status = 'ONLINE',
        int? updated,
      }) {
        final at = _at.add(Duration(seconds: second));
        return MonitoringSnapshot(
          generatedAt: at,
          freshness: const Duration(minutes: 10),
          traps: [
            _trap(
              status: status,
              gasRuntime: GasSensorRuntime(
                bootId: boot,
                state: state,
                warmupUntil: state == 'WARMING_UP'
                    ? at.add(const Duration(minutes: 1))
                    : null,
                updatedAt: _at.add(Duration(seconds: updated ?? second)),
                rawAdc: raw,
              ),
            ),
          ],
        );
      }

      repository.current = current('boot-a', 'READY', raw: 456);
      final auth = _auth();
      await tester.pumpWidget(_app(repository, auth));
      await tester.pumpAndSettle();
      final panel = find.byKey(const Key('sensor-summary-panel'));
      Finder value(String text) =>
          find.descendant(of: panel, matching: find.text(text));
      expect(value('456 ADC (raw)'), findsOneWidget);
      expect(value('725 calibrated'), findsNothing);
      repository.current = current('boot-b', 'WARMING_UP');
      await tester.pump(MonitoringScreen.refreshInterval);
      await tester.pumpAndSettle();
      expect(value('Sensor warming up'), findsOneWidget);
      expect(value('456 ADC (raw)'), findsNothing);
      expect(value('725 calibrated'), findsNothing);
      expect(value('31.5 °C'), findsOneWidget);
      await tester.pump(const Duration(seconds: 30));
      await tester.pumpAndSettle();
      expect(value('Sensor warming up'), findsOneWidget);
      await tester.pump(const Duration(seconds: 30));
      await tester.pumpAndSettle();
      expect(value('Please check wiring of sensor'), findsOneWidget);
      repository.current = current('boot-b', 'READY', raw: 777, second: 61);
      await tester.pump(MonitoringScreen.refreshInterval);
      await tester.pumpAndSettle();
      expect(value('777 ADC (raw)'), findsOneWidget);
      repository.current = current('boot-c', 'WARMING_UP', second: 62);
      await tester.pump(MonitoringScreen.refreshInterval);
      await tester.pumpAndSettle();
      expect(value('Sensor warming up'), findsOneWidget);
      expect(value('777 ADC (raw)'), findsNothing);
      expect(value('Please check wiring of sensor'), findsNothing);
      repository.current = current(
        'boot-c',
        'READY',
        raw: 888,
        second: 200,
        updated: 62,
      );
      await tester.pump(MonitoringScreen.refreshInterval);
      await tester.pumpAndSettle();
      expect(value('Waiting for Gas status'), findsOneWidget);
      expect(value('888 ADC (raw)'), findsNothing);
      repository.current = current(
        'boot-c',
        'READY',
        raw: 888,
        second: 201,
        status: 'OFFLINE',
      );
      await tester.pump(MonitoringScreen.refreshInterval);
      await tester.pumpAndSettle();
      expect(value('Device offline'), findsOneWidget);
      await tester.pumpWidget(const SizedBox());
      auth.api.close();
      auth.dispose();
    },
  );

  test('Gas runtime model parses warm-up metadata and valid zero', () {
    final runtime = GasSensorRuntime.fromJson({
      'boot_id': 'boot-a',
      'state': 'READY',
      'gas_raw': 0,
      'warmup_until': null,
      'updated_at': '2026-10-04T06:35:00Z',
    });
    expect(runtime.rawAdc, 0);
    expect(
      () => GasSensorRuntime.fromJson({
        'boot_id': 'boot-a',
        'state': 'WARMING_UP',
        'gas_raw': null,
        'warmup_until': null,
        'updated_at': '2026-10-04T06:35:00Z',
      }),
      throwsFormatException,
    );
  });

  testWidgets('range and sensor selectors reload history without fake zeroes', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(900, 1500);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    final repository = _FakeRepository();
    final auth = _auth();
    await tester.pumpWidget(_app(repository, auth));
    await tester.pumpAndSettle();
    await tester.scrollUntilVisible(
      find.byKey(const Key('range-1h')),
      300,
      scrollable: find.byType(Scrollable).first,
    );
    for (final range in [
      HistoryRange.lastHour,
      HistoryRange.last24Hours,
      HistoryRange.last7Days,
      HistoryRange.last30Days,
      HistoryRange.today,
    ]) {
      final rangeChip = find.byKey(Key('range-${range.apiValue}'));
      await tester.ensureVisible(rangeChip);
      await tester.pumpAndSettle();
      await tester.tap(rangeChip);
      await tester.pumpAndSettle();
      expect(repository.historyCalls.last.$2, range);
    }
    expect(find.byType(LineChart), findsOneWidget);
    var chart = tester.widget<LineChart>(find.byType(LineChart));
    final spots = chart.data.lineBarsData.single.spots;
    expect(spots.any((spot) => spot.isNull()), isTrue);
    expect(
      spots.where((spot) => !spot.isNull()).any((spot) => spot.y == 0),
      isFalse,
    );
    await tester.tap(find.byKey(const Key('metric-temperature')));
    await tester.pump();
    chart = tester.widget<LineChart>(find.byType(LineChart));
    expect(chart.data.lineBarsData.single.spots, isNotEmpty);
    await tester.pumpWidget(const SizedBox());
    auth.api.close();
    auth.dispose();
  });

  testWidgets('manual refresh retains data on a temporary failure', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(900, 1500);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    final repository = _FakeRepository();
    final auth = _auth();
    await tester.pumpWidget(_app(repository, auth));
    await tester.pumpAndSettle();
    repository.currentError = const ApiException(
      'Server unavailable.',
      type: ApiErrorType.networkError,
    );
    await tester.tap(find.byTooltip('Refresh monitoring'));
    await tester.pumpAndSettle();
    expect(find.text('58%'), findsWidgets);
    expect(find.textContaining('Unable to refresh'), findsOneWidget);
    expect(auth.state, AuthState.signedIn);
    await tester.pumpWidget(const SizedBox());
    auth.api.close();
    auth.dispose();
  });

  testWidgets(
    'history page taps replace ten readings and preserve the page on failure',
    (tester) async {
      tester.view.physicalSize = const Size(900, 1500);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);
      final repository = _FakeRepository()
        ..history = (trap, range, page) => _history(
          trapId: trap,
          range: range,
          page: page,
          pages: 2,
          readings: List.generate(
            10,
            (index) =>
                _point(index + (page - 1) * 10, waste: page == 1 ? 58 : 30),
          ),
        );
      final auth = _auth();
      await tester.pumpWidget(_app(repository, auth));
      await tester.pumpAndSettle();
      final indicator = find.byKey(const Key('history-page-indicator'));
      await tester.dragUntilVisible(
        indicator,
        find.byType(ListView),
        const Offset(0, -500),
      );
      Future<void> openPage(int page) async {
        final button = find.byKey(Key('history-page-$page'));
        await tester.ensureVisible(button);
        await tester.pumpAndSettle();
        await tester.tap(button);
        await tester.pumpAndSettle();
      }

      expect(find.byKey(const Key('history-next-page')), findsNothing);
      expect(find.byKey(const Key('history-previous-page')), findsNothing);
      expect(find.text('Page 1 of 2'), findsOneWidget);
      expect(find.byType(ListTile), findsNWidgets(10));
      await openPage(2);
      expect(repository.historyCalls.last.$3, 2);
      expect(find.text('Page 2 of 2'), findsOneWidget);
      expect(find.byType(ListTile), findsNWidgets(10));
      final callsAtEnd = repository.historyCalls.length;
      await openPage(2);
      expect(repository.historyCalls.length, callsAtEnd);
      expect(find.textContaining('30%'), findsWidgets);
      repository.historyError = const ApiException(
        'History temporarily unavailable.',
        type: ApiErrorType.serverError,
      );
      await openPage(1);
      expect(find.text('Page 2 of 2'), findsOneWidget);
      expect(find.byType(ListTile), findsNWidgets(10));
      repository.historyError = null;
      await openPage(1);
      expect(repository.historyCalls.last.$3, 1);
      expect(find.text('Page 1 of 2'), findsOneWidget);
      expect(find.byType(ListTile), findsNWidgets(10));
      await tester.pumpWidget(const SizedBox());
      auth.api.close();
      auth.dispose();
    },
  );

  testWidgets('all history pages scroll horizontally on a narrow phone', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(390, 844);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    final repository = _FakeRepository()
      ..history = (trap, range, page) => _history(
        trapId: trap,
        range: range,
        page: page,
        pages: 30,
        readings: List.generate(10, (index) => _point((page - 1) * 10 + index)),
      );
    final auth = _auth();
    await tester.pumpWidget(_app(repository, auth));
    await tester.pumpAndSettle();
    final strip = find.byKey(const Key('history-page-strip'));
    final outer = find.byWidgetPredicate(
      (widget) => widget is ListView && widget.scrollDirection == Axis.vertical,
    );
    await tester.dragUntilVisible(strip, outer, const Offset(0, -400));
    await tester.ensureVisible(strip);
    await tester.pumpAndSettle();
    final calls = repository.historyCalls.length;
    final last = find.byKey(const Key('history-page-30'));
    await tester.dragUntilVisible(last, strip, const Offset(-250, 0));
    await tester.ensureVisible(last);
    await tester.pumpAndSettle();
    expect(repository.historyCalls.length, calls);
    expect(find.text('Page 1 of 30'), findsOneWidget);
    await tester.tap(last);
    await tester.pumpAndSettle();
    expect(repository.historyCalls.last.$3, 30);
    expect(find.text('Page 30 of 30'), findsOneWidget);
    final first = find.byKey(const Key('history-page-1'));
    await tester.dragUntilVisible(first, strip, const Offset(250, 0));
    await tester.ensureVisible(first);
    await tester.pumpAndSettle();
    expect(repository.historyCalls.last.$3, 30);
    await tester.tap(first);
    await tester.pumpAndSettle();
    expect(repository.historyCalls.last.$3, 1);
    expect(find.byType(ListTile), findsNWidgets(10));
    expect(find.text('Previous'), findsNothing);
    expect(find.text('Next'), findsNothing);
    expect(tester.takeException(), isNull);
    await tester.pumpWidget(const SizedBox());
    auth.api.close();
    auth.dispose();
  });

  testWidgets('auto refresh uses one timer and stops when disposed', (
    tester,
  ) async {
    final repository = _FakeRepository();
    final auth = _auth();
    await tester.pumpWidget(_app(repository, auth));
    await tester.pumpAndSettle();
    expect(repository.currentCalls, 1);
    await tester.pump(MonitoringScreen.refreshInterval);
    await tester.pump();
    expect(repository.currentCalls, 2);
    await tester.pumpWidget(const SizedBox());
    await tester.pump(MonitoringScreen.refreshInterval * 2);
    expect(repository.currentCalls, 2);
    auth.api.close();
    auth.dispose();
  });

  for (final size in [const Size(320, 568), const Size(1024, 768)]) {
    testWidgets(
      'monitoring remains scrollable at ${size.width}x${size.height}',
      (tester) async {
        tester.view.physicalSize = size;
        tester.view.devicePixelRatio = 1;
        addTearDown(tester.view.resetPhysicalSize);
        addTearDown(tester.view.resetDevicePixelRatio);
        final repository = _FakeRepository();
        final auth = _auth();
        await tester.pumpWidget(_app(repository, auth));
        await tester.pumpAndSettle();
        expect(tester.takeException(), isNull);
        await tester.fling(find.byType(ListView).first, const Offset(0, -1200), 2000);
        await tester.pumpAndSettle();
        expect(tester.takeException(), isNull);
        expect(find.text('Telemetry History'), findsOneWidget);
        final rangeRects = [
          for (final range in HistoryRange.values)
            tester.getRect(find.byKey(Key('range-${range.apiValue}'))),
        ];
        expect(rangeRects[0].top, rangeRects[1].top);
        expect(rangeRects[1].top, rangeRects[2].top);
        expect(rangeRects[3].top, rangeRects[4].top);
        expect(rangeRects[4].top, rangeRects[5].top);
        expect(rangeRects[3].top, greaterThan(rangeRects[0].bottom));
        for (var column = 0; column < 3; column++) {
          expect(
            rangeRects[column].left,
            closeTo(rangeRects[column + 3].left, 0.1),
          );
          expect(rangeRects[column].width, closeTo(rangeRects[0].width, 0.1));
        }
        await tester.pumpWidget(const SizedBox());
        auth.api.close();
        auth.dispose();
      },
    );
  }
}
