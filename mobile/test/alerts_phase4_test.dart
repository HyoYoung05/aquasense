import 'dart:convert';

import 'package:aquasense_mobile/models/alert.dart';
import 'package:aquasense_mobile/models/monitoring.dart';
import 'package:aquasense_mobile/screens/alerts/alert_detail_screen.dart';
import 'package:aquasense_mobile/screens/alerts/alerts_screen.dart';
import 'package:aquasense_mobile/screens/monitoring/monitoring_screen.dart';
import 'package:aquasense_mobile/services/alerts_service.dart';
import 'package:aquasense_mobile/services/api_service.dart';
import 'package:aquasense_mobile/services/auth_service.dart';
import 'package:aquasense_mobile/services/monitoring_service.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/testing.dart';
import 'package:http/http.dart' as http;

final _now = DateTime.utc(2026, 10, 4, 6, 35);

class _Store implements TokenStore {
  String? value = 'a' * 64;
  @override
  Future<void> clear() async => value = null;
  @override
  Future<String?> read() async => value;
  @override
  Future<void> write(String token) async => value = token;
}

AuthService _auth() {
  final a = AuthService(
    ApiService(
      baseUrl: 'https://api.example.test/api/mobile',
      client: MockClient((_) async => http.Response('{}', 500)),
    ),
    _Store(),
  );
  a.state = AuthState.signedIn;
  return a;
}

AlertItem _alert({
  int id = 1,
  String type = 'CRITICAL_LEVEL',
  String severity = 'CRITICAL',
  String status = 'ACTIVE',
  String? device = 'AQS-001',
  double? threshold = 90,
}) => AlertItem(
  id: id,
  establishmentId: 1,
  greaseTrapId: 12,
  triggerCount: 2,
  alertType: type,
  severity: severity,
  status: status,
  message: type == 'DEVICE_OFFLINE'
      ? 'AQS-001 has stopped reporting data.'
      : 'The grease trap needs attention.',
  label: alertTypeLabel(type),
  businessName: 'Demo Kusina',
  greaseTrapName: 'Main Trap',
  deviceCode: device,
  sensorName: type == 'DEVICE_OFFLINE' ? null : 'waste_level_percent',
  sensorValue: type == 'DEVICE_OFFLINE' ? null : 92,
  thresholdValue: threshold,
  firstTriggeredAt: _now.subtract(const Duration(hours: 1)),
  lastTriggeredAt: _now,
  acknowledgedAt: status == 'ACKNOWLEDGED' ? _now : null,
  resolvedAt: status == 'RESOLVED' ? _now : null,
);
AlertPage _page({List<AlertItem>? alerts, int page = 1, int pages = 1}) {
  final list =
      alerts ??
      [_alert(), _alert(id: 2, type: 'HIGH_LEVEL', severity: 'WARNING')];
  return AlertPage(
    alerts: list,
    summary: AlertCounts(
      activeCount: list.where((a) => a.status == 'ACTIVE').length,
      acknowledgedCount: list.where((a) => a.status == 'ACKNOWLEDGED').length,
      resolvedCount: list.where((a) => a.status == 'RESOLVED').length,
      unresolvedCount: list.where((a) => a.status != 'RESOLVED').length,
      criticalCount: list
          .where((a) => a.status != 'RESOLVED' && a.severity == 'CRITICAL')
          .length,
      warningCount: list
          .where((a) => a.status != 'RESOLVED' && a.severity == 'WARNING')
          .length,
      infoCount: 0,
    ),
    traps: const [
      AlertTrap(id: 12, name: 'Main Trap', businessName: 'Demo Kusina'),
      AlertTrap(id: 13, name: 'Back Trap', businessName: 'Demo Kusina'),
    ],
    page: page,
    pages: pages,
    total: list.length,
  );
}

class _Repo implements AlertsRepository {
  AlertPage page = _page();
  ApiException? error;
  int listCalls = 0, detailCalls = 0;
  AlertStatusFilter? status;
  AlertSeverityFilter? severity;
  int? trap;
  @override
  Future<AlertItem> loadAlert(int id) async {
    detailCalls++;
    if (error case final e?) throw e;
    return page.alerts.firstWhere(
      (a) => a.id == id,
      orElse: () => _alert(id: id),
    );
  }

  @override
  Future<AlertPage> loadAlerts({
    required AlertStatusFilter status,
    required AlertSeverityFilter severity,
    required AlertDateRange range,
    DateTime? from,
    DateTime? to,
    int? greaseTrapId,
    int page = 1,
  }) async {
    listCalls++;
    this.status = status;
    this.severity = severity;
    trap = greaseTrapId;
    if (error case final e?) throw e;
    return this.page;
  }
}

Widget _app(_Repo r, {Size size = const Size(390, 844)}) => MediaQuery(
  data: MediaQueryData(size: size),
  child: MaterialApp(
    home: Scaffold(
      body: AlertsScreen(auth: _auth(), repository: r),
    ),
  ),
);

void main() {
  test('alert model supports known, unknown and nullable future values', () {
    expect(alertTypeLabel('DEVICE_OFFLINE'), 'Monitoring Device Offline');
    expect(alertTypeLabel('SYSTEM_ALERT'), 'System Alert');
    final a = AlertItem.fromJson({
      'id': 1,
      'establishment_id': 1,
      'grease_trap_id': 2,
      'alert_type': 'SYSTEM_ALERT',
      'severity': 'FUTURE',
      'status': 'QUEUED',
      'message': null,
      'label': null,
      'business_name': 'Kitchen',
      'grease_trap_name': 'Trap',
      'device_code': null,
      'sensor_name': null,
      'sensor_value': null,
      'threshold_value': null,
      'first_triggered_at': '2026-10-04T00:00:00Z',
      'last_triggered_at': '2026-10-04T01:00:00Z',
    });
    expect(a.label, 'System Alert');
    expect(a.severity, 'FUTURE');
    expect(a.deviceCode, isNull);
    expect(a.thresholdValue, isNull);
  });
  test('alert 401 uses centralized session expiration', () async {
    final store = _Store();
    final auth = AuthService(
      ApiService(
        baseUrl: 'https://api.example.test/api/mobile',
        client: MockClient(
          (request) async => request.url.path.endsWith('login.php')
              ? http.Response(
                  jsonEncode({
                    'success': true,
                    'data': {
                      'token': 'a' * 64,
                      'user': {
                        'id': 1,
                        'full_name': 'Owner',
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
      ),
      store,
    );
    await auth.login('owner@example.test', 'correct');
    await expectLater(
      AlertsService(auth).loadAlerts(
        status: AlertStatusFilter.unresolved,
        severity: AlertSeverityFilter.all,
        range: AlertDateRange.last30Days,
      ),
      throwsA(isA<ApiException>()),
    );
    expect(auth.state, AuthState.signedOut);
    expect(store.value, isNull);
    auth.api.close();
    auth.dispose();
  });
  testWidgets(
    'Alerts loads active warning and critical records with text severity',
    (tester) async {
      final r = _Repo();
      await tester.pumpWidget(_app(r));
      await tester.pumpAndSettle();
      expect(find.text('Owner Alerts'), findsOneWidget);
      expect(find.text('Critical Waste Level'), findsOneWidget);
      expect(find.text('High Waste Level'), findsOneWidget);
      expect(find.text('CRITICAL'), findsWidgets);
      expect(find.text('WARNING'), findsWidgets);
      expect(find.text('AQS-001'), findsWidgets);
    },
  );
  testWidgets('empty active and resolved history states are honest', (
    tester,
  ) async {
    final r = _Repo()..page = _page(alerts: []);
    await tester.pumpWidget(_app(r));
    await tester.pumpAndSettle();
    expect(find.text('No active alerts.'), findsOneWidget);
    await tester.tap(find.byType(DropdownButtonFormField<AlertStatusFilter>));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Resolved').last);
    await tester.pumpAndSettle();
    expect(find.text('No alert history is available yet.'), findsOneWidget);
  });
  testWidgets('status severity and trap filters reach repository', (
    tester,
  ) async {
    final r = _Repo();
    await tester.pumpWidget(_app(r));
    await tester.pumpAndSettle();
    await tester.tap(find.text('All severities'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Warning').last);
    await tester.pumpAndSettle();
    expect(r.severity, AlertSeverityFilter.warning);
    await tester.tap(find.text('All authorized traps'));
    await tester.pumpAndSettle();
    await tester.tap(find.textContaining('Main Trap').last);
    await tester.pumpAndSettle();
    expect(r.trap, 12);
  });
  testWidgets('alert detail is read only and handles missing optional values', (
    tester,
  ) async {
    final r = _Repo()
      ..page = _page(alerts: [_alert(device: null, threshold: null)]);
    await tester.pumpWidget(_app(r));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Critical Waste Level'));
    await tester.pumpAndSettle();
    expect(find.byType(AlertDetailScreen), findsOneWidget);
    expect(find.text('Not available'), findsWidgets);
    expect(
      find.textContaining('authorized Barangay personnel'),
      findsOneWidget,
    );
    expect(find.text('Acknowledge'), findsNothing);
    expect(find.text('Resolve'), findsNothing);
  });
  testWidgets(
    'device offline, emulsion, overflow and acknowledged/resolved values render',
    (tester) async {
      final alerts = [
        _alert(type: 'DEVICE_OFFLINE', severity: 'WARNING'),
        _alert(id: 2, type: 'EMULSION_WARNING', status: 'ACKNOWLEDGED'),
        _alert(id: 3, type: 'OVERFLOW_WARNING', status: 'RESOLVED'),
      ];
      final r = _Repo()..page = _page(alerts: alerts);
      await tester.pumpWidget(_app(r));
      await tester.pumpAndSettle();
      expect(find.text('Monitoring Device Offline'), findsOneWidget);
      expect(find.text('Emulsion Warning'), findsOneWidget);
      await tester.scrollUntilVisible(find.text('Overflow Warning'), 250);
      expect(find.text('Overflow Warning'), findsOneWidget);
      expect(find.text('ACKNOWLEDGED'), findsOneWidget);
      expect(find.text('RESOLVED'), findsOneWidget);
    },
  );
  testWidgets('refresh failure preserves old alert data', (tester) async {
    final r = _Repo();
    await tester.pumpWidget(_app(r));
    await tester.pumpAndSettle();
    r.error = const ApiException('offline', type: ApiErrorType.networkError);
    await tester.fling(find.byType(ListView), const Offset(0, 300), 1000);
    await tester.pump();
    await tester.pump(const Duration(seconds: 1));
    expect(find.text('Critical Waste Level'), findsOneWidget);
    expect(find.textContaining('Unable to refresh alerts'), findsOneWidget);
  });
  testWidgets('single polling timer stops when Alerts is disposed', (
    tester,
  ) async {
    final r = _Repo();
    await tester.pumpWidget(_app(r));
    await tester.pumpAndSettle();
    final initial = r.listCalls;
    await tester.pump(AlertsScreen.refreshInterval);
    await tester.pump();
    expect(r.listCalls, initial + 1);
    await tester.pumpWidget(const MaterialApp(home: SizedBox()));
    await tester.pump(AlertsScreen.refreshInterval * 2);
    expect(r.listCalls, initial + 1);
  });
  testWidgets('Alerts remains responsive on small and tablet sizes', (
    tester,
  ) async {
    for (final size in [const Size(320, 568), const Size(1024, 768)]) {
      final r = _Repo();
      await tester.binding.setSurfaceSize(size);
      await tester.pumpWidget(_app(r, size: size));
      await tester.pumpAndSettle();
      expect(find.text('Owner Alerts'), findsOneWidget);
      expect(tester.takeException(), isNull);
    }
    await tester.binding.setSurfaceSize(null);
  });
  testWidgets('Monitoring active alert banner opens owner alert detail', (
    tester,
  ) async {
    await tester.binding.setSurfaceSize(const Size(1024, 768));
    final alerts = _Repo()..page = _page(alerts: [_alert()]);
    final reading = MonitoringReading(
      wasteLevel: 92,
      ultrasonicDistance: 7,
      temperature: 30,
      turbidity: null,
      flowRate: null,
      gasValue: null,
      condition: 'CRITICAL',
      isSimulated: false,
      isTest: true,
      recordedAt: _now,
    );
    final current = MonitoringSnapshot(
      generatedAt: _now,
      freshness: const Duration(minutes: 10),
      traps: [
        GreaseTrapMonitoring(
          establishmentId: 1,
          businessName: 'Demo Kusina',
          greaseTrapId: 12,
          greaseTrapName: 'Main Trap',
          deviceCode: 'AQS-001',
          deviceName: null,
          firmwareVersion: null,
          deviceStatus: 'ONLINE',
          lastSeenAt: _now,
          sensorState: 'CRITICAL',
          isStale: false,
          reading: reading,
          activeAlert: AlertPreview(
            id: 1,
            alertType: 'CRITICAL_LEVEL',
            label: 'Critical Waste Level',
            severity: 'CRITICAL',
            status: 'ACTIVE',
            message: 'Needs attention',
            lastTriggeredAt: _now,
          ),
        ),
      ],
    );
    final monitoring = _MonitoringRepo(current);
    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: MonitoringScreen(
            auth: _auth(),
            repository: monitoring,
            alertsRepository: alerts,
          ),
        ),
      ),
    );
    await tester.pumpAndSettle();
    expect(find.text('View Alert'), findsOneWidget);
    final button = find.byKey(const Key('monitoring-alert-1')).at(0);
    await tester.ensureVisible(button);
    await tester.pumpAndSettle();
    await tester.tap(button);
    await tester.pumpAndSettle();
    expect(find.byType(AlertDetailScreen), findsOneWidget);
    await tester.binding.setSurfaceSize(null);
  });
}

class _MonitoringRepo implements MonitoringRepository {
  final MonitoringSnapshot snapshot;
  _MonitoringRepo(this.snapshot);
  @override
  Future<MonitoringSnapshot> loadCurrent() async => snapshot;
  @override
  Future<TelemetryHistory> loadHistory({
    required int greaseTrapId,
    required HistoryRange range,
    DateTime? from,
    DateTime? to,
    int page = 1,
  }) async => TelemetryHistory(
    trapId: greaseTrapId,
    trapName: 'Main Trap',
    businessName: 'Demo Kusina',
    range: range,
    from: _now.subtract(const Duration(days: 1)),
    to: _now,
    page: 1,
    pages: 1,
    total: 0,
    readings: const [],
  );
}
