import 'dart:async';
import 'dart:convert';
import 'dart:typed_data';

import 'package:aquasense_mobile/models/oil_surrender.dart';
import 'package:aquasense_mobile/screens/oil_surrender/new_surrender_screen.dart';
import 'package:aquasense_mobile/screens/oil_surrender/oil_surrender_detail_screen.dart';
import 'package:aquasense_mobile/screens/oil_surrender/oil_surrender_screen.dart';
import 'package:aquasense_mobile/services/api_service.dart';
import 'package:aquasense_mobile/services/auth_service.dart';
import 'package:aquasense_mobile/services/oil_surrender_service.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:image_picker/image_picker.dart';

final _png = base64Decode(
  'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
);
final _submitted = DateTime.utc(2026, 10, 5, 4, 30);

OilSurrender _surrender({
  int id = 1,
  String status = 'PENDING',
  String? remarks,
  SurrenderPhoto? photo,
}) => OilSurrender(
  id: id,
  transactionCode: 'OS-20261005-${id.toString().padLeft(4, '0')}',
  submissionUuid: '123e4567-e89b-42d3-a456-42661417400$id',
  businessName: 'Demo Kusina',
  greaseTrapName: 'Main Trap',
  quantity: 5,
  unit: 'L',
  notes: 'Sealed container',
  submittedAt: _submitted,
  status: status,
  verificationStatus: status,
  reviewRemarks: remarks,
  reviewedAt: status == 'PENDING' ? null : _submitted,
  approvedAt: status == 'APPROVED' ? _submitted : null,
  rejectedAt: status == 'REJECTED' ? _submitted : null,
  photo: photo,
);

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
  final auth = AuthService(
    ApiService(
      baseUrl: 'https://api.example.test/api/mobile',
      client: MockClient((_) async => http.Response('{}', 500)),
    ),
    _Store(),
  );
  auth.state = AuthState.signedIn;
  return auth;
}

class _Picker implements EvidencePicker {
  XFile? next = XFile.fromData(
    _png,
    path: 'evidence.png',
    mimeType: 'image/png',
  );
  ImageSource? source;
  @override
  Future<XFile?> pick(ImageSource source) async {
    this.source = source;
    return next;
  }
}

class _Repo implements OilSurrenderRepository {
  List<OilSurrender> records = [_surrender()];
  ApiException? historyError;
  int historyCalls = 0;
  int submitCalls = 0;
  bool failFirstSubmission = false;
  final submissionUuids = <String>[];
  Completer<void>? submissionGate;

  @override
  Future<OilSurrenderHistory> loadHistory(SurrenderStatusFilter filter) async {
    historyCalls++;
    if (historyError case final error?) throw error;
    return OilSurrenderHistory(
      filter.apiValue == null
          ? records
          : records.where((item) => item.status == filter.apiValue).toList(),
    );
  }

  @override
  Future<OilSurrender> loadDetail(int id) async =>
      records.firstWhere((item) => item.id == id);

  @override
  Future<Uint8List> loadPhoto(int photoId) async => _png;

  @override
  Future<SurrenderSubmissionResult> submit({
    required String submissionUuid,
    required double quantity,
    required String unit,
    required int? greaseTrapId,
    required String notes,
    required EvidencePhoto photo,
  }) async {
    submitCalls++;
    submissionUuids.add(submissionUuid);
    if (submissionGate case final gate?) await gate.future;
    if (failFirstSubmission && submitCalls == 1) {
      throw const ApiException(
        'Submission status could not be confirmed. Refresh your surrender history before submitting again.',
        type: ApiErrorType.networkError,
      );
    }
    final item = _surrender(id: records.length + 1);
    records = [item, ...records];
    return SurrenderSubmissionResult(
      surrender: item,
      idempotentReplay: submitCalls > 1,
    );
  }
}

const _traps = [
  SurrenderTrapOption(id: 12, name: 'Main Trap', businessName: 'Demo Kusina'),
];

Widget _historyApp(_Repo repo, {_Picker? picker}) => MaterialApp(
  home: Scaffold(
    body: OilSurrenderScreen(
      auth: _auth(),
      repository: repo,
      evidencePicker: picker,
      traps: _traps,
      establishmentCount: 1,
    ),
  ),
);

Widget _formApp(
  _Repo repo,
  _Picker picker, {
  List<SurrenderTrapOption> traps = _traps,
}) => MaterialApp(
  home: NewSurrenderScreen(
    repository: repo,
    traps: traps,
    establishmentCount: 1,
    evidencePicker: picker,
  ),
);

Future<void> _selectGallery(WidgetTester tester) async {
  await tester.tap(find.byKey(const Key('choose_photo')));
  await tester.pumpAndSettle();
  await tester.tap(find.text('Choose from gallery'));
  await tester.pumpAndSettle();
}

Future<void> _confirmSubmit(WidgetTester tester) async {
  await tester.ensureVisible(find.byKey(const Key('submit_surrender')));
  await tester.pumpAndSettle();
  await tester.tap(find.byKey(const Key('submit_surrender')));
  await tester.pumpAndSettle();
  await tester.tap(find.widgetWithText(FilledButton, 'Submit'));
  await tester.pump();
}

Future<void> _useTallSurface(WidgetTester tester) async {
  await tester.binding.setSurfaceSize(const Size(800, 1000));
  addTearDown(() => tester.binding.setSurfaceSize(null));
}

void main() {
  test(
    'surrender model accepts backend statuses and nullable owner-safe fields',
    () {
      final value = OilSurrender.fromJson({
        'id': 7,
        'transaction_code': 'OS-7',
        'submission_uuid': 'uuid',
        'business_name': 'Kitchen',
        'grease_trap_name': null,
        'oil_quantity': 0.5,
        'oil_unit': 'kg',
        'notes': null,
        'submitted_at': '2026-10-05T00:00:00Z',
        'status': 'UNDER_REVIEW',
        'verification_status': 'MANUAL_REVIEW',
        'review_remarks': null,
        'reviewed_at': null,
        'approved_at': null,
        'rejected_at': null,
        'photo': null,
      });
      expect(value.quantity, 0.5);
      expect(value.greaseTrapName, isNull);
      expect(surrenderStatusLabel(value.status), 'UNDER REVIEW');
      expect(surrenderStatusLabel('FUTURE_STATE'), 'FUTURE STATE');
      expect(surrenderStatusMessage('FUTURE_STATE'), contains('server'));
    },
  );

  test(
    'photo validation accepts supported files and rejects bad type and size',
    () async {
      final valid = await EvidencePhoto.validate(
        XFile.fromData(_png, path: 'proof.WEBP'),
      );
      expect(valid.extension, 'webp');
      await expectLater(
        EvidencePhoto.validate(XFile.fromData(_png, path: 'proof.gif')),
        throwsA(isA<ApiException>()),
      );
      await expectLater(
        EvidencePhoto.validate(
          XFile.fromData(
            Uint8List(surrenderPhotoLimitBytes + 1),
            path: 'large.jpg',
          ),
        ),
        throwsA(isA<ApiException>()),
      );
    },
  );

  test('UUID generator creates RFC 4122 version 4 values', () {
    final id = OilSurrenderService.newSubmissionUuid();
    expect(
      id,
      matches(
        RegExp(
          r'^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$',
        ),
      ),
    );
  });

  test('protected photo 401 expires the centralized owner session', () async {
    final store = _Store();
    final auth = AuthService(
      ApiService(
        baseUrl: 'https://api.example.test/api/mobile',
        client: MockClient((request) async {
          if (request.url.path.endsWith('login.php')) {
            return http.Response(
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
            );
          }
          return http.Response(
            jsonEncode({'success': false, 'message': 'Expired'}),
            401,
          );
        }),
      ),
      store,
    );
    await auth.login('owner@example.test', 'correct');
    await expectLater(
      auth.getBytes('oil-surrender-photo.php?id=9'),
      throwsA(isA<ApiException>()),
    );
    expect(auth.state, AuthState.signedOut);
    expect(store.value, isNull);
    auth.api.close();
    auth.dispose();
  });

  testWidgets('history shows counts, records, filters, and read-only status', (
    tester,
  ) async {
    final repo = _Repo()
      ..records = [
        _surrender(status: 'PENDING'),
        _surrender(id: 2, status: 'UNDER_REVIEW'),
        _surrender(id: 3, status: 'APPROVED'),
        _surrender(id: 4, status: 'REJECTED', remarks: 'Photo was unclear.'),
      ];
    await tester.pumpWidget(_historyApp(repo));
    await tester.pumpAndSettle();
    expect(find.text('1 pending'), findsOneWidget);
    expect(find.text('OS-20261005-0001'), findsOneWidget);
    expect(find.text('UNDER REVIEW'), findsOneWidget);
    expect(find.text('Approve'), findsNothing);
    expect(find.text('Reject'), findsNothing);
    await tester.tap(find.text('Approved'));
    await tester.pumpAndSettle();
    expect(find.text('OS-20261005-0003'), findsOneWidget);
    expect(find.text('OS-20261005-0001'), findsNothing);
  });

  testWidgets('history refresh failure keeps prior records', (tester) async {
    final repo = _Repo();
    await tester.pumpWidget(_historyApp(repo));
    await tester.pumpAndSettle();
    repo.historyError = const ApiException(
      'Server unavailable',
      type: ApiErrorType.serverUnavailable,
    );
    await tester.fling(find.byType(ListView).first, const Offset(0, 300), 1000);
    await tester.pump();
    await tester.pump(const Duration(seconds: 1));
    expect(find.text('OS-20261005-0001'), findsOneWidget);
    expect(find.text('Server unavailable'), findsOneWidget);
  });

  testWidgets('form validates quantity, trap, and required photo', (
    tester,
  ) async {
    await _useTallSurface(tester);
    final repo = _Repo();
    final picker = _Picker();
    await tester.pumpWidget(
      _formApp(
        repo,
        picker,
        traps: const [
          SurrenderTrapOption(id: 12, name: 'A', businessName: 'Kitchen'),
          SurrenderTrapOption(id: 13, name: 'B', businessName: 'Kitchen'),
        ],
      ),
    );
    await tester.ensureVisible(find.byKey(const Key('submit_surrender')));
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('submit_surrender')));
    await tester.pump();
    expect(find.text('Enter the oil quantity.'), findsOneWidget);
    expect(
      find.text('Select the grease trap for this surrender.'),
      findsOneWidget,
    );
    expect(find.text('Photo evidence is required.'), findsOneWidget);
    await tester.enterText(find.byKey(const Key('surrender_quantity')), '0');
    await tester.ensureVisible(find.byKey(const Key('submit_surrender')));
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('submit_surrender')));
    await tester.pump();
    expect(find.text('Quantity must be greater than zero.'), findsOneWidget);
    expect(repo.submitCalls, 0);
  });

  testWidgets('camera/gallery selection previews and removes evidence', (
    tester,
  ) async {
    await _useTallSurface(tester);
    final picker = _Picker();
    await tester.pumpWidget(_formApp(_Repo(), picker));
    await _selectGallery(tester);
    expect(picker.source, ImageSource.gallery);
    expect(find.byKey(const Key('photo_preview')), findsOneWidget);
    expect(find.text('evidence.png'), findsOneWidget);
    await tester.ensureVisible(find.byTooltip('Remove photo'));
    await tester.pumpAndSettle();
    await tester.tap(find.byTooltip('Remove photo'));
    await tester.pump();
    expect(find.byKey(const Key('choose_photo')), findsOneWidget);
  });

  testWidgets(
    'timeout preserves form and retry uses the same idempotency UUID',
    (tester) async {
      await _useTallSurface(tester);
      final repo = _Repo()..failFirstSubmission = true;
      await tester.pumpWidget(_formApp(repo, _Picker()));
      await tester.enterText(
        find.byKey(const Key('surrender_quantity')),
        '5.25',
      );
      await _selectGallery(tester);
      await _confirmSubmit(tester);
      await tester.pumpAndSettle();
      expect(find.textContaining('could not be confirmed'), findsOneWidget);
      expect(find.text('5.25'), findsOneWidget);
      expect(find.byKey(const Key('photo_preview')), findsOneWidget);
      await _confirmSubmit(tester);
      await tester.pump(const Duration(milliseconds: 500));
      expect(repo.submitCalls, 2);
      expect(repo.submissionUuids.toSet(), hasLength(1));
      expect(find.text('Submission already received'), findsOneWidget);
    },
  );

  testWidgets('submitting disables repeated taps', (tester) async {
    await _useTallSurface(tester);
    final repo = _Repo()..submissionGate = Completer<void>();
    await tester.pumpWidget(_formApp(repo, _Picker()));
    await tester.enterText(find.byKey(const Key('surrender_quantity')), '2');
    await _selectGallery(tester);
    await _confirmSubmit(tester);
    await tester.pump();
    expect(find.text('Submitting…'), findsOneWidget);
    expect(repo.submitCalls, 1);
    repo.submissionGate!.complete();
    await tester.pump(const Duration(milliseconds: 500));
    expect(find.text('Oil surrender submitted'), findsOneWidget);
  });

  testWidgets(
    'detail displays remarks and authenticated photo without owner actions',
    (tester) async {
      final repo = _Repo()
        ..records = [
          _surrender(
            status: 'REJECTED',
            remarks: 'Photo evidence was unclear.',
            photo: SurrenderPhoto(id: 9, uploadedAt: _submitted),
          ),
        ];
      await tester.pumpWidget(
        MaterialApp(
          home: OilSurrenderDetailScreen(surrenderId: 1, repository: repo),
        ),
      );
      await tester.pumpAndSettle();
      expect(find.text('Photo evidence was unclear.'), findsOneWidget);
      expect(find.text('Reviewed by'), findsOneWidget);
      expect(find.text('Barangay'), findsOneWidget);
      expect(find.byType(Image), findsOneWidget);
      expect(find.text('Approve'), findsNothing);
      expect(find.text('Delete'), findsNothing);
    },
  );

  test('multipart sends exact fields, photo, and bearer header', () async {
    late http.MultipartRequest captured;
    final api = ApiService(
      baseUrl: 'https://api.example.test/api/mobile',
      client: MockClient.streaming((request, body) async {
        captured = request as http.MultipartRequest;
        await body.drain<void>();
        return http.StreamedResponse(
          Stream.value(
            utf8.encode(
              jsonEncode({
                'success': true,
                'data': {'accepted': true},
              }),
            ),
          ),
          201,
        );
      }),
    );
    final data = await api.postMultipart(
      'oil-surrender.php',
      token: 'secret-token',
      fields: const {
        'submission_uuid': 'uuid',
        'oil_quantity': '5',
        'oil_unit': 'L',
        'grease_trap_id': '12',
        'notes': 'Ready',
      },
      fileField: 'photo',
      filePath: '',
      filename: 'proof.png',
      fileBytes: _png,
    );
    expect(data['accepted'], true);
    expect(captured.headers['Authorization'], 'Bearer secret-token');
    expect(captured.fields['oil_unit'], 'L');
    expect(captured.fields.containsKey('establishment_id'), false);
    expect(captured.files.single.field, 'photo');
    expect(captured.files.single.filename, 'proof.png');
    api.close();
  });
}
