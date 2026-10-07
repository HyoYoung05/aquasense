import 'dart:convert';

import 'package:aquasense_mobile/app.dart';
import 'package:aquasense_mobile/models/dashboard_snapshot.dart';
import 'package:aquasense_mobile/models/establishment.dart';
import 'package:aquasense_mobile/models/incentive_summary.dart';
import 'package:aquasense_mobile/models/oil_surrender_summary.dart';
import 'package:aquasense_mobile/screens/home/dashboard_content.dart';
import 'package:aquasense_mobile/screens/home/home_screen.dart';
import 'package:aquasense_mobile/screens/login/login_screen.dart';
import 'package:aquasense_mobile/services/api_service.dart';
import 'package:aquasense_mobile/services/auth_service.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';

const apiBaseUrl = 'https://api.example.test/api/mobile';
const ownerPayload = {
  'id': 24,
  'full_name': 'Taylor Cruz',
  'email': 'owner@aquasense.test',
};

Map<String, dynamic> dashboardPayload({
  double? wasteLevel = 58,
  double? distance = 12.4,
  double? temperature,
  String condition = 'MEDIUM',
  String deviceStatus = 'ONLINE',
  bool stale = false,
  bool includeAlert = true,
  bool includeEstablishment = true,
  bool includeTrap = true,
}) => {
  'user': ownerPayload,
  'generated_at': '2026-10-03T03:05:00Z',
  'freshness_seconds': 600,
  'unknown_future_field': true,
  'alerts': includeAlert
      ? [
          {
            'id': 9,
            'alert_type': 'LEVEL_HIGH',
            'severity': condition == 'CRITICAL' ? 'CRITICAL' : 'WARNING',
            'message': 'Grease trap requires attention.',
            'status': 'ACTIVE',
            'last_triggered_at': '2026-10-03 03:04:00',
            'grease_trap_id': 12,
            'grease_trap_name': 'Kitchen trap',
            'establishment_id': 1,
            'business_name': 'Demo Kusina',
            'label': condition == 'CRITICAL' ? 'Critical level' : 'High level',
            'unknown_optional_field': 'ignored',
          },
        ]
      : <Object>[],
  'establishments': includeEstablishment
      ? [
          {
            'id': 1,
            'business_name': 'Demo Kusina',
            'traps': includeTrap
                ? [
                    {
                      'id': 12,
                      'name': 'Kitchen trap',
                      'status': wasteLevel == null ? 'NO DATA' : condition,
                      'device_code': 'AQS-001',
                      'device_status': deviceStatus,
                      'is_stale': stale,
                      'reading': wasteLevel == null
                          ? null
                          : {
                              'waste_level_percent': wasteLevel,
                              'temperature_c': temperature,
                              'ultrasonic_distance_cm': distance,
                              'is_test': true,
                              'is_simulated': false,
                              'recorded_at': '2026-10-03T03:00:00Z',
                              'future_sensor': null,
                            },
                    },
                  ]
                : <Object>[],
          },
        ]
      : <Object>[],
};

Map<String, dynamic> surrenderPayload({bool populated = true}) => {
  'surrenders': populated
      ? [
          {
            'id': 31,
            'transaction_code': 'OS-20261003-A1B2C3D4',
            'oil_quantity': 5,
            'oil_unit': 'L',
            'status': 'PENDING',
            'submitted_at': '2026-10-03T02:00:00Z',
            'future_field': 'ignored',
          },
        ]
      : <Object>[],
};

Map<String, dynamic> incentivePayload({bool populated = true}) => {
  'summary': populated
      ? [
          {'unit': 'kg', 'earned': 13, 'distributed': 8, 'pending': 5},
        ]
      : <Object>[],
  'transactions': populated
      ? [
          {
            'transaction_code': 'INC-20261003-A1B2C3D4',
            'rice_quantity': 5,
            'rice_unit': 'kg',
            'status': 'CALCULATED',
            'processed_at': '2026-10-03T02:30:00Z',
          },
        ]
      : <Object>[],
};

DashboardSnapshot snapshot({
  Map<String, dynamic>? dashboard,
  Map<String, dynamic>? surrenders,
  Map<String, dynamic>? incentives,
}) => DashboardSnapshot(
  dashboard: OwnerDashboard.fromJson(dashboard ?? dashboardPayload()),
  oilSurrenders: OilSurrenderSummary.fromJson(surrenders ?? surrenderPayload()),
  incentives: IncentiveSummary.fromJson(incentives ?? incentivePayload()),
);

http.Response ok(Map<String, dynamic> data) =>
    http.Response(jsonEncode({'success': true, 'data': data}), 200);

class Phase2Store implements TokenStore {
  String? token = 'a' * 64;

  @override
  Future<void> clear() async => token = null;

  @override
  Future<String?> read() async => token;

  @override
  Future<void> write(String value) async => token = value;
}

class Phase2Server {
  double level = 58;
  bool offlineDashboard = false;
  bool denyDashboard = false;

  Future<http.Response> handle(http.Request request) async {
    if (request.url.path.endsWith('profile.php')) {
      return ok({'user': ownerPayload});
    }
    if (request.url.path.endsWith('logout.php')) {
      return ok({'message': 'Signed out'});
    }
    if (request.url.path.endsWith('dashboard.php')) {
      if (offlineDashboard) throw http.ClientException('offline');
      if (denyDashboard) return http.Response('{}', 401);
      return ok(dashboardPayload(wasteLevel: level));
    }
    if (request.url.path.endsWith('oil-surrenders.php')) {
      return ok(surrenderPayload());
    }
    if (request.url.path.endsWith('incentives.php')) {
      return ok(incentivePayload());
    }
    return http.Response('{}', 404);
  }
}

void main() {
  test('dashboard model parses the final contract and unknown fields', () {
    final result = snapshot();
    expect(result.dashboard.user.fullName, 'Taylor Cruz');
    expect(result.dashboard.establishments.single.businessName, 'Demo Kusina');
    expect(
      result.dashboard.establishments.single.traps.single.deviceCode,
      'AQS-001',
    );
    expect(result.dashboard.alerts.single.label, 'High level');
    expect(
      result.oilSurrenders.latest?.transactionCode,
      'OS-20261003-A1B2C3D4',
    );
    expect(result.incentives.units.single.pending, 5);
  });

  test('valid zero telemetry remains a reading', () {
    final result = snapshot(
      dashboard: dashboardPayload(wasteLevel: 0, distance: 30),
    );
    final reading =
        result.dashboard.establishments.single.traps.single.reading!;
    expect(reading.wasteLevel, 0);
    expect(reading.ultrasonicDistance, 30);
  });

  test('missing telemetry and null optional sensors parse safely', () {
    final missing = snapshot(
      dashboard: dashboardPayload(wasteLevel: null, includeAlert: false),
    );
    expect(
      missing.dashboard.establishments.single.traps.single.reading,
      isNull,
    );
    final optional = snapshot(
      dashboard: dashboardPayload(temperature: null, distance: null),
    );
    expect(
      optional
          .dashboard
          .establishments
          .single
          .traps
          .single
          .reading!
          .temperature,
      isNull,
    );
    expect(
      optional
          .dashboard
          .establishments
          .single
          .traps
          .single
          .reading!
          .ultrasonicDistance,
      isNull,
    );
  });

  test('incentive summaries preserve separate reward units', () {
    final result = snapshot(
      incentives: {
        'summary': [
          {'unit': 'kg', 'earned': 4, 'distributed': 1, 'pending': 3},
          {'unit': 'g', 'earned': 500, 'distributed': 0, 'pending': 500},
        ],
        'transactions': <Object>[],
      },
    );
    expect(result.incentives.units.map((item) => item.unit), ['kg', 'g']);
    expect(result.incentives.units[0].pending, 3);
    expect(result.incentives.units[1].pending, 500);
  });

  testWidgets('dashboard displays complete owner summary', (tester) async {
    int? openedAlert;
    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: DashboardContent(
            snapshot: snapshot(),
            refreshing: false,
            refreshError: null,
            onRefresh: () async {},
            onNavigate: (_) {},
            onOpenAlert: (id) => openedAlert = id,
          ),
        ),
      ),
    );
    expect(find.text('Welcome, Taylor Cruz'), findsOneWidget);
    expect(find.text('Demo Kusina'), findsWidgets);
    expect(find.text('Kitchen trap'), findsWidgets);
    expect(find.text('58%'), findsOneWidget);
    expect(find.text('12.4 cm'), findsOneWidget);
    expect(find.text('Device ONLINE'), findsOneWidget);
    expect(find.text('Last sensor update'), findsOneWidget);
    expect(find.text('1 active alert'), findsOneWidget);
    await tester.ensureVisible(find.text('High level'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('High level'));
    expect(openedAlert, 9);
    expect(find.textContaining('OS-20261003-A1B2C3D4'), findsOneWidget);
    expect(
      find.text('5 kg pending · 8 kg distributed · 13 kg earned'),
      findsOneWidget,
    );
    expect(tester.takeException(), isNull);
  });

  testWidgets('zero reading displays 0 percent', (tester) async {
    await tester.pumpWidget(
      MaterialApp(
        home: DashboardContent(
          snapshot: snapshot(
            dashboard: dashboardPayload(wasteLevel: 0, distance: 30),
          ),
          refreshing: false,
          refreshError: null,
          onRefresh: () async {},
          onNavigate: (_) {},
        ),
      ),
    );
    expect(find.text('0%'), findsOneWidget);
    expect(find.text('No sensor data has been received yet.'), findsNothing);
  });

  testWidgets('missing telemetry has an explicit no-data state', (
    tester,
  ) async {
    await tester.pumpWidget(
      MaterialApp(
        home: DashboardContent(
          snapshot: snapshot(
            dashboard: dashboardPayload(wasteLevel: null, includeAlert: false),
          ),
          refreshing: false,
          refreshError: null,
          onRefresh: () async {},
          onNavigate: (_) {},
        ),
      ),
    );
    expect(find.text('No sensor data has been received yet.'), findsWidgets);
    expect(find.text('0%'), findsNothing);
    expect(tester.takeException(), isNull);
  });

  testWidgets('offline device shows backend status and last update', (
    tester,
  ) async {
    await tester.pumpWidget(
      MaterialApp(
        home: DashboardContent(
          snapshot: snapshot(
            dashboard: dashboardPayload(
              deviceStatus: 'OFFLINE',
              condition: 'OFFLINE',
              stale: true,
            ),
          ),
          refreshing: false,
          refreshError: null,
          onRefresh: () async {},
          onNavigate: (_) {},
        ),
      ),
    );
    expect(find.text('OFFLINE'), findsOneWidget);
    expect(find.text('Device OFFLINE'), findsOneWidget);
    expect(find.text('Last sensor update'), findsOneWidget);
  });

  testWidgets('empty alert surrender and incentive summaries are neutral', (
    tester,
  ) async {
    await tester.pumpWidget(
      MaterialApp(
        home: DashboardContent(
          snapshot: snapshot(
            dashboard: dashboardPayload(includeAlert: false),
            surrenders: surrenderPayload(populated: false),
            incentives: incentivePayload(populated: false),
          ),
          refreshing: false,
          refreshError: null,
          onRefresh: () async {},
          onNavigate: (_) {},
        ),
      ),
    );
    expect(find.text('No active alerts'), findsOneWidget);
    expect(find.text('No oil surrender records yet.'), findsOneWidget);
    expect(find.text('No incentive records yet.'), findsOneWidget);
    expect(find.textContaining('System Healthy'), findsNothing);
  });

  testWidgets('critical backend condition is prominent', (tester) async {
    await tester.pumpWidget(
      MaterialApp(
        home: DashboardContent(
          snapshot: snapshot(
            dashboard: dashboardPayload(wasteLevel: 95, condition: 'CRITICAL'),
          ),
          refreshing: false,
          refreshError: null,
          onRefresh: () async {},
          onNavigate: (_) {},
        ),
      ),
    );
    expect(find.text('Critical attention required'), findsOneWidget);
    expect(find.text('CRITICAL'), findsWidgets);
  });

  testWidgets('quick actions navigate without routes or 404s', (tester) async {
    tester.view.physicalSize = const Size(800, 2200);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    var destination = -1;
    await tester.pumpWidget(
      MaterialApp(
        home: DashboardContent(
          snapshot: snapshot(),
          refreshing: false,
          refreshError: null,
          onRefresh: () async {},
          onNavigate: (value) => destination = value,
        ),
      ),
    );
    final monitoring = find.widgetWithText(OutlinedButton, 'Monitoring');
    await tester.tap(monitoring);
    expect(destination, 1);
    final profile = find.widgetWithText(OutlinedButton, 'Profile');
    await tester.tap(profile);
    expect(destination, 5);
  });

  group('dashboard refresh and authentication', () {
    late Phase2Store store;
    late Phase2Server server;
    late AuthService auth;

    setUp(() {
      store = Phase2Store();
      server = Phase2Server();
      auth = AuthService(
        ApiService(client: MockClient(server.handle), baseUrl: apiBaseUrl),
        store,
      );
    });

    tearDown(() {
      auth.api.close();
      auth.dispose();
    });

    testWidgets('authenticated dashboard loads and refreshes updated data', (
      tester,
    ) async {
      await tester.pumpWidget(OwnerApp(auth: auth));
      await tester.pumpAndSettle();
      expect(find.byType(HomeScreen), findsOneWidget);
      expect(find.text('58%'), findsOneWidget);
      server.level = 72;
      await tester.tap(find.byTooltip('Refresh dashboard'));
      await tester.pumpAndSettle();
      expect(find.text('72%'), findsOneWidget);
      expect(find.text('58%'), findsNothing);
    });

    testWidgets('refresh failure preserves the last dashboard and token', (
      tester,
    ) async {
      await tester.pumpWidget(OwnerApp(auth: auth));
      await tester.pumpAndSettle();
      expect(find.text('58%'), findsOneWidget);
      server.offlineDashboard = true;
      await tester.tap(find.byTooltip('Refresh dashboard'));
      await tester.pumpAndSettle();
      expect(find.text('58%'), findsOneWidget);
      expect(
        find.textContaining('Showing the last loaded data'),
        findsOneWidget,
      );
      expect(store.token, isNotNull);
      expect(auth.state, AuthState.signedIn);
    });

    testWidgets('dashboard 401 uses centralized session expiration', (
      tester,
    ) async {
      await tester.pumpWidget(OwnerApp(auth: auth));
      await tester.pumpAndSettle();
      server.denyDashboard = true;
      await tester.tap(find.byTooltip('Refresh dashboard'));
      await tester.pumpAndSettle();
      expect(find.byType(LoginScreen), findsOneWidget);
      expect(find.textContaining('session expired'), findsOneWidget);
      expect(store.token, isNull);
    });

    testWidgets('initial dashboard network failure keeps valid session', (
      tester,
    ) async {
      server.offlineDashboard = true;
      await tester.pumpWidget(OwnerApp(auth: auth));
      await tester.pumpAndSettle();
      expect(find.text('Unable to load dashboard'), findsOneWidget);
      expect(store.token, isNotNull);
      expect(auth.state, AuthState.signedIn);
    });
  });
}
