import 'dart:convert';
import 'dart:typed_data';

import 'package:aquasense_mobile/models/incentive_summary.dart';
import 'package:aquasense_mobile/models/oil_surrender.dart';
import 'package:aquasense_mobile/screens/incentives/incentive_detail_screen.dart';
import 'package:aquasense_mobile/screens/incentives/incentives_screen.dart';
import 'package:aquasense_mobile/screens/home/home_screen.dart';
import 'package:aquasense_mobile/screens/oil_surrender/oil_surrender_detail_screen.dart';
import 'package:aquasense_mobile/services/api_service.dart';
import 'package:aquasense_mobile/services/auth_service.dart';
import 'package:aquasense_mobile/services/incentives_service.dart';
import 'package:aquasense_mobile/services/oil_surrender_service.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';

const _baseUrl = 'https://api.example.test/api/mobile';

Map<String, dynamic> _payload({bool empty = false}) => {
  'summary': empty
      ? <Object>[]
      : [
          {'unit': 'kg', 'earned': 8.5, 'distributed': 3, 'pending': 5.5},
          {'unit': 'g', 'earned': 250, 'distributed': 250, 'pending': 0},
        ],
  'transactions': empty
      ? <Object>[]
      : [
          {
            'transaction_code': 'INC-003',
            'surrender_code': 'OS-003',
            'business_name': 'Demo Kusina',
            'oil_quantity': 5,
            'oil_unit': 'L',
            'rice_quantity': 5.5,
            'rice_unit': 'kg',
            'status': 'CALCULATED',
            'processed_at': '2026-10-06T03:00:00Z',
            'distributed_at': null,
          },
          {
            'transaction_code': 'INC-002',
            'surrender_code': 'OS-002',
            'business_name': 'Demo Kusina',
            'oil_quantity': 2,
            'oil_unit': 'L',
            'rice_quantity': 250,
            'rice_unit': 'g',
            'status': 'DISTRIBUTED',
            'processed_at': '2026-10-05T03:00:00Z',
            'distributed_at': '2026-10-06T04:00:00Z',
          },
          {
            'transaction_code': 'INC-001',
            'surrender_code': 'OS-001',
            'business_name': 'Demo Kusina',
            'oil_quantity': 1,
            'oil_unit': 'L',
            'rice_quantity': 3,
            'rice_unit': 'kg',
            'status': 'CANCELLED',
            'processed_at': '2026-10-04T03:00:00Z',
            'distributed_at': null,
          },
        ],
};

class _Store implements TokenStore {
  String? value = 'a' * 64;
  @override
  Future<void> clear() async => value = null;
  @override
  Future<String?> read() async => value;
  @override
  Future<void> write(String token) async => value = token;
}

AuthService _auth() => AuthService(
  ApiService(
    baseUrl: _baseUrl,
    client: MockClient((_) async => http.Response('{}', 500)),
  ),
  _Store(),
);

class _IncentiveRepo implements IncentivesRepository {
  IncentiveSummary value = IncentiveSummary.fromJson(_payload());
  ApiException? error;
  int calls = 0;

  @override
  Future<IncentiveSummary> load() async {
    calls++;
    if (error case final exception?) throw exception;
    return value;
  }
}

class _OilRepo implements OilSurrenderRepository {
  final OilSurrender value;
  _OilRepo(this.value);
  @override
  Future<OilSurrender> loadDetail(int id) async => value;
  @override
  Future<OilSurrenderHistory> loadHistory(SurrenderStatusFilter filter) async =>
      OilSurrenderHistory([value]);
  @override
  Future<Uint8List> loadPhoto(int photoId) async => Uint8List(0);
  @override
  Future<SurrenderSubmissionResult> submit({
    required String submissionUuid,
    required double quantity,
    required String unit,
    required int? greaseTrapId,
    required String notes,
    required EvidencePhoto photo,
  }) => throw UnimplementedError();
}

OilSurrender _approved(String code) => OilSurrender(
  id: 1,
  transactionCode: code,
  submissionUuid: '123e4567-e89b-42d3-a456-426614174001',
  businessName: 'Demo Kusina',
  greaseTrapName: 'Main Trap',
  quantity: 5,
  unit: 'L',
  notes: null,
  submittedAt: DateTime.utc(2026, 10, 6),
  status: 'APPROVED',
  verificationStatus: 'APPROVED',
  reviewRemarks: null,
  reviewedAt: DateTime.utc(2026, 10, 6),
  approvedAt: DateTime.utc(2026, 10, 6),
  rejectedAt: null,
  photo: null,
);

void main() {
  test('model parses exact backend fields and keeps reward units separate', () {
    final result = IncentiveSummary.fromJson(_payload());
    expect(result.units.map((item) => item.unit), ['kg', 'g']);
    expect(result.units[0].pending, 5.5);
    expect(result.transactions[0].surrenderCode, 'OS-003');
    expect(result.transactions[0].oilQuantity, 5);
    expect(result.transactions[1].distributedAt, isNotNull);
  });

  test('optional owner-safe fields may be null', () {
    final result = IncentiveTransaction.fromJson({
      'transaction_code': 'INC-1',
      'rice_quantity': 2,
      'rice_unit': 'kg',
      'status': 'CALCULATED',
      'processed_at': '2026-10-06T00:00:00Z',
    });
    expect(result.surrenderCode, isNull);
    expect(result.distributedAt, isNull);
  });

  test('status mapping covers backend values and future values safely', () {
    expect(incentiveStatusLabel('CALCULATED'), 'Pending Distribution');
    expect(
      incentiveStatusLabel('APPROVED_FOR_DISTRIBUTION'),
      'Ready for Distribution',
    );
    expect(incentiveStatusLabel('DISTRIBUTED'), 'Distributed');
    expect(incentiveStatusLabel('CANCELLED'), 'Cancelled');
    expect(incentiveStatusLabel('FUTURE_STATE'), 'Future State');
  });

  test(
    'filters group both pending backend statuses without changing records',
    () {
      final data = IncentiveSummary.fromJson({
        'summary': <Object>[],
        'transactions': [
          ...(_payload()['transactions'] as List),
          {
            ...(_payload()['transactions'] as List).first
                as Map<String, dynamic>,
            'transaction_code': 'INC-004',
            'status': 'APPROVED_FOR_DISTRIBUTION',
          },
        ],
      });
      expect(data.filtered(IncentiveStatusFilter.pending), hasLength(2));
      expect(
        data.filtered(IncentiveStatusFilter.distributed).single.transactionCode,
        'INC-002',
      );
      expect(
        data.filtered(IncentiveStatusFilter.cancelled).single.transactionCode,
        'INC-001',
      );
    },
  );

  testWidgets('screen displays summaries, history, and exact server values', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(800, 3200);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    final repo = _IncentiveRepo();
    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: IncentivesScreen(auth: _auth(), repository: repo),
        ),
      ),
    );
    await tester.pumpAndSettle();
    expect(find.text('Pending'), findsWidgets);
    expect(find.text('Distributed'), findsWidgets);
    expect(find.text('INC-003'), findsOneWidget);
    expect(find.text('Rice reward: 5.5 kg'), findsOneWidget);
    expect(find.text('Pending Distribution'), findsWidgets);
  });

  testWidgets(
    'history filters show pending, distributed, and cancelled records',
    (tester) async {
      tester.view.physicalSize = const Size(800, 1800);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);
      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: IncentivesScreen(auth: _auth(), repository: _IncentiveRepo()),
          ),
        ),
      );
      await tester.pumpAndSettle();
      await tester.tap(find.widgetWithText(ChoiceChip, 'Distributed'));
      await tester.pump();
      expect(find.text('INC-002'), findsOneWidget);
      expect(find.text('INC-003'), findsNothing);
      await tester.tap(find.widgetWithText(ChoiceChip, 'Cancelled'));
      await tester.pump();
      expect(find.text('INC-001'), findsOneWidget);
    },
  );

  testWidgets('empty and initial error states are explicit', (tester) async {
    final repo = _IncentiveRepo()
      ..value = IncentiveSummary.fromJson(_payload(empty: true));
    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: IncentivesScreen(auth: _auth(), repository: repo),
        ),
      ),
    );
    await tester.pumpAndSettle();
    expect(find.text('No incentive records yet.'), findsWidgets);
    repo.error = const ApiException('Offline', type: ApiErrorType.networkError);
    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: IncentivesScreen(
            key: const ValueKey('error'),
            auth: _auth(),
            repository: repo,
          ),
        ),
      ),
    );
    await tester.pumpAndSettle();
    expect(find.text('Unable to load incentive information'), findsOneWidget);
  });

  testWidgets('refresh failure retains previously loaded data', (tester) async {
    final repo = _IncentiveRepo();
    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: IncentivesScreen(auth: _auth(), repository: repo),
        ),
      ),
    );
    await tester.pumpAndSettle();
    repo.error = const ApiException('Offline', type: ApiErrorType.networkError);
    await tester.tap(find.byTooltip('Refresh incentives'));
    await tester.pumpAndSettle();
    expect(find.text('INC-003'), findsOneWidget);
    expect(find.textContaining('Showing the last loaded data'), findsOneWidget);
  });

  testWidgets('pull-to-refresh reloads backend incentive data', (tester) async {
    tester.view.physicalSize = const Size(800, 1800);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    final repo = _IncentiveRepo();
    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: IncentivesScreen(auth: _auth(), repository: repo),
        ),
      ),
    );
    await tester.pumpAndSettle();
    expect(repo.calls, 1);
    await tester.drag(find.byType(ListView), const Offset(0, 500));
    await tester.pumpAndSettle();
    expect(repo.calls, 2);
  });

  testWidgets('app resume refreshes incentives without polling', (
    tester,
  ) async {
    final repo = _IncentiveRepo();
    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: IncentivesScreen(auth: _auth(), repository: repo),
        ),
      ),
    );
    await tester.pumpAndSettle();
    expect(repo.calls, 1);
    tester.binding.handleAppLifecycleStateChanged(AppLifecycleState.paused);
    tester.binding.handleAppLifecycleStateChanged(AppLifecycleState.resumed);
    await tester.pumpAndSettle();
    expect(repo.calls, 2);
    await tester.pump(const Duration(seconds: 10));
    expect(repo.calls, 2);
  });

  testWidgets('main navigation opens the functional Incentives module', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(1200, 1200);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    final store = _Store();
    final auth = AuthService(
      ApiService(
        baseUrl: _baseUrl,
        client: MockClient((request) async {
          final data = switch (request.url.pathSegments.last) {
            'profile.php' => {
              'user': {
                'id': 1,
                'full_name': 'Owner',
                'email': 'owner@example.test',
              },
            },
            'dashboard.php' => {
              'user': {
                'id': 1,
                'full_name': 'Owner',
                'email': 'owner@example.test',
              },
              'generated_at': '2026-10-06T00:00:00Z',
              'freshness_seconds': 600,
              'alerts': <Object>[],
              'establishments': <Object>[],
            },
            'oil-surrenders.php' => {'surrenders': <Object>[]},
            'incentives.php' => _payload(),
            _ => <String, dynamic>{},
          };
          return http.Response(
            jsonEncode({'success': true, 'data': data}),
            200,
          );
        }),
      ),
      store,
    );
    await auth.restore();
    final incentives = _IncentiveRepo();
    await tester.pumpWidget(
      MaterialApp(
        home: HomeScreen(
          auth: auth,
          onLogout: () async {},
          incentivesRepository: incentives,
        ),
      ),
    );
    await tester.pumpAndSettle();
    final destination = find.descendant(
      of: find.byType(NavigationRail),
      matching: find.text('Incentives'),
    );
    await tester.tap(destination);
    await tester.pumpAndSettle();
    expect(find.text('Rice incentive summary'), findsOneWidget);
    expect(incentives.calls, 1);
  });

  testWidgets(
    'detail displays relationship, distribution, and no owner action',
    (tester) async {
      final item = IncentiveSummary.fromJson(_payload()).transactions[1];
      await tester.pumpWidget(
        MaterialApp(home: IncentiveDetailScreen(transaction: item)),
      );
      await tester.pumpAndSettle();
      expect(find.text('OS-002'), findsOneWidget);
      expect(find.text('250 g'), findsOneWidget);
      expect(find.text('Distributed'), findsWidgets);
      expect(find.text('Read-only record'), findsOneWidget);
      expect(find.textContaining('Claim'), findsNothing);
      expect(find.textContaining('Redeem'), findsNothing);
    },
  );

  testWidgets('approved surrender links matching incentive', (tester) async {
    tester.view.physicalSize = const Size(800, 1800);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    await tester.pumpWidget(
      MaterialApp(
        home: OilSurrenderDetailScreen(
          surrenderId: 1,
          repository: _OilRepo(_approved('OS-003')),
          incentivesRepository: _IncentiveRepo(),
        ),
      ),
    );
    await tester.pumpAndSettle();
    expect(find.text('Rice Incentive Available'), findsOneWidget);
    expect(find.text('View Incentive'), findsOneWidget);
  });

  testWidgets('approved surrender without backend incentive stays pending', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(800, 1800);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    await tester.pumpWidget(
      MaterialApp(
        home: OilSurrenderDetailScreen(
          surrenderId: 1,
          repository: _OilRepo(_approved('OS-NONE')),
          incentivesRepository: _IncentiveRepo(),
        ),
      ),
    );
    await tester.pumpAndSettle();
    expect(find.text('Awaiting Processing'), findsOneWidget);
    expect(
      find.text('Approved. Incentive processing is pending.'),
      findsOneWidget,
    );
    expect(find.textContaining('0 kg'), findsNothing);
  });

  test('service uses owner endpoint and converts malformed data', () async {
    final store = _Store();
    final auth = AuthService(
      ApiService(
        baseUrl: _baseUrl,
        client: MockClient((request) async {
          if (request.url.path.endsWith('profile.php')) {
            return http.Response(
              jsonEncode({
                'success': true,
                'data': {
                  'user': {
                    'id': 1,
                    'full_name': 'Owner',
                    'email': 'owner@example.test',
                  },
                },
              }),
              200,
            );
          }
          expect(request.url.path, endsWith('/incentives.php'));
          return http.Response(
            jsonEncode({
              'success': true,
              'data': {'summary': 'bad', 'transactions': []},
            }),
            200,
          );
        }),
      ),
      store,
    );
    await auth.restore();
    await expectLater(
      IncentivesService(auth).load(),
      throwsA(
        isA<ApiException>().having(
          (error) => error.type,
          'type',
          ApiErrorType.serverError,
        ),
      ),
    );
  });

  test('401 uses centralized authentication expiry and clears token', () async {
    final store = _Store();
    final auth = AuthService(
      ApiService(
        baseUrl: _baseUrl,
        client: MockClient((request) async {
          if (request.url.path.endsWith('profile.php')) {
            return http.Response(
              jsonEncode({
                'success': true,
                'data': {
                  'user': {
                    'id': 1,
                    'full_name': 'Owner',
                    'email': 'owner@example.test',
                  },
                },
              }),
              200,
            );
          }
          return http.Response('{}', 401);
        }),
      ),
      store,
    );
    await auth.restore();
    await expectLater(
      IncentivesService(auth).load(),
      throwsA(
        isA<ApiException>().having(
          (error) => error.type,
          'type',
          ApiErrorType.unauthorized,
        ),
      ),
    );
    expect(auth.state, AuthState.signedOut);
    expect(store.value, isNull);
  });
}
