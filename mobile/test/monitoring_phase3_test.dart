import 'dart:convert';

import 'package:aquasense_mobile/models/monitoring.dart';
import 'package:aquasense_mobile/screens/monitoring/monitoring_screen.dart';
import 'package:aquasense_mobile/services/api_service.dart';
import 'package:aquasense_mobile/services/auth_service.dart';
import 'package:aquasense_mobile/services/monitoring_service.dart';
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

MonitoringReading _reading({double? waste = 58}) => MonitoringReading(
  wasteLevel: waste,
  ultrasonicDistance: 12.4,
  temperature: 31.5,
  turbidity: 410,
  flowRate: 1.8,
  gasValue: 725,
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
  flowRate: waste == null ? null : 1.8,
  gasValue: waste == null ? null : 725,
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
              'flow_rate_lpm': null,
              'gas_value': null,
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
              'flow_rate_lpm': 1.8,
              'gas_value': 725,
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
      expect(find.text('725 raw'), findsWidgets);
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
    expect(find.text('Not connected'), findsNWidgets(4));
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
      await tester.tap(find.byKey(Key('range-${range.apiValue}')));
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

  testWidgets('history pagination appends a bounded second page', (
    tester,
  ) async {
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
        readings: page == 1 ? [_point(0)] : [_point(30, waste: 30)],
      );
    final auth = _auth();
    await tester.pumpWidget(_app(repository, auth));
    await tester.pumpAndSettle();
    final loadMore = find.widgetWithText(OutlinedButton, 'Load more (1/2)');
    await tester.dragUntilVisible(
      loadMore,
      find.byType(ListView),
      const Offset(0, -500),
    );
    await tester.ensureVisible(loadMore);
    await tester.pumpAndSettle();
    tester.widget<OutlinedButton>(loadMore).onPressed!();
    await tester.pumpAndSettle();
    expect(repository.historyCalls.last.$3, 2);
    expect(find.textContaining('30%'), findsWidgets);
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
        await tester.fling(find.byType(ListView), const Offset(0, -1200), 2000);
        await tester.pumpAndSettle();
        expect(tester.takeException(), isNull);
        expect(find.text('Telemetry History'), findsOneWidget);
        await tester.pumpWidget(const SizedBox());
        auth.api.close();
        auth.dispose();
      },
    );
  }
}
