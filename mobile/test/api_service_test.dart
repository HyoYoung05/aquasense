import 'dart:convert';

import 'package:aquasense_mobile/services/api_service.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';

const baseUrl = 'https://api.example.test/api/mobile';

http.Response envelope(Map<String, dynamic> data) =>
    http.Response(jsonEncode({'success': true, 'data': data}), 200);

void main() {
  test('CORS guidance is debug-web only', () {
    expect(
      ApiService.connectionFailureMessage(debugMode: true, web: true),
      allOf(contains('CORS'), contains('OPTIONS'), contains('API_BASE_URL')),
    );
    expect(
      ApiService.connectionFailureMessage(debugMode: false, web: true),
      'Unable to connect to the AQUASENSE+ server.',
    );
    expect(
      ApiService.connectionFailureMessage(debugMode: true, web: false),
      'Unable to connect to the AQUASENSE+ server.',
    );
  });

  test('GET and POST use shared JSON and authorization headers', () async {
    var calls = 0;
    final api = ApiService(
      baseUrl: baseUrl,
      client: MockClient((request) async {
        calls++;
        expect(request.headers['Accept'], 'application/json');
        expect(request.headers['Authorization'], 'Bearer secret-token');
        if (request.method == 'POST') {
          expect(request.headers['Content-Type'], contains('application/json'));
          expect(jsonDecode(request.body), {'answer': 42});
        }
        return envelope({'ok': true});
      }),
    );
    expect(await api.get('profile.php', token: 'secret-token'), {'ok': true});
    expect(
      await api.post('login.php', token: 'secret-token', body: {'answer': 42}),
      {'ok': true},
    );
    expect(calls, 2);
    api.close();
  });

  test('safe backend validation message is preserved', () async {
    final api = ApiService(
      baseUrl: baseUrl,
      client: MockClient(
        (_) async => http.Response(
          jsonEncode({'success': false, 'message': 'Email is required.'}),
          422,
        ),
      ),
    );
    await expectLater(
      api.post('login.php'),
      throwsA(
        isA<ApiException>()
            .having((error) => error.type, 'type', ApiErrorType.validationError)
            .having((error) => error.message, 'message', 'Email is required.'),
      ),
    );
    api.close();
  });

  test(
    'forbidden, not-found, unavailable, and server failures are distinct',
    () async {
      for (final entry in {
        403: ApiErrorType.forbidden,
        404: ApiErrorType.notFound,
        503: ApiErrorType.serverUnavailable,
        500: ApiErrorType.serverError,
      }.entries) {
        final api = ApiService(
          baseUrl: baseUrl,
          client: MockClient(
            (_) async => http.Response(
              jsonEncode({'success': false, 'message': 'Safe message'}),
              entry.key,
            ),
          ),
        );
        await expectLater(
          api.get('profile.php'),
          throwsA(
            isA<ApiException>().having(
              (error) => error.type,
              'type',
              entry.value,
            ),
          ),
        );
        api.close();
      }
    },
  );

  test(
    'unsafe JSON error messages are replaced with private-safe text',
    () async {
      for (final unsafe in [
        'SQLSTATE[HY000] database failed',
        'PHP Warning: include C:\\private\\config.php',
        'SocketException: connection refused',
        '<!DOCTYPE html><html>server error</html>',
        'Authorization: Bearer private-token',
      ]) {
        final api = ApiService(
          baseUrl: baseUrl,
          client: MockClient(
            (_) async => http.Response(
              jsonEncode({'success': false, 'message': unsafe}),
              422,
            ),
          ),
        );
        await expectLater(
          api.post('oil-surrender.php'),
          throwsA(
            isA<ApiException>()
                .having(
                  (error) => error.type,
                  'type',
                  ApiErrorType.validationError,
                )
                .having(
                  (error) => error.message,
                  'message',
                  isNot(contains(unsafe)),
                ),
          ),
        );
        api.close();
      }
    },
  );

  test('timeout becomes a network error', () async {
    final api = ApiService(
      baseUrl: baseUrl,
      timeout: const Duration(milliseconds: 5),
      client: MockClient((_) async {
        await Future<void>.delayed(const Duration(milliseconds: 30));
        return envelope({});
      }),
    );
    await expectLater(
      api.get('profile.php'),
      throwsA(
        isA<ApiException>().having(
          (error) => error.type,
          'type',
          ApiErrorType.networkError,
        ),
      ),
    );
    api.close();
  });

  test('invalid JSON becomes a safe server error', () async {
    final api = ApiService(
      baseUrl: baseUrl,
      client: MockClient(
        (_) async => http.Response('<html>SQLSTATE private detail</html>', 200),
      ),
    );
    await expectLater(
      api.get('profile.php'),
      throwsA(
        isA<ApiException>()
            .having((error) => error.type, 'type', ApiErrorType.serverError)
            .having(
              (error) => error.message,
              'message',
              isNot(contains('SQLSTATE')),
            ),
      ),
    );
    api.close();
  });

  test('401 expires authentication even when the error body is malformed', () {
    final api = ApiService(
      baseUrl: baseUrl,
      client: MockClient(
        (_) async => http.Response('<html>expired</html>', 401),
      ),
    );
    addTearDown(api.close);
    expect(
      api.get('profile.php'),
      throwsA(
        isA<ApiException>().having(
          (error) => error.type,
          'type',
          ApiErrorType.unauthorized,
        ),
      ),
    );
  });

  test('development log excludes token and request body', () async {
    final messages = <String>[];
    final originalDebugPrint = debugPrint;
    debugPrint = (message, {wrapWidth}) {
      if (message != null) messages.add(message);
    };
    addTearDown(() => debugPrint = originalDebugPrint);
    final api = ApiService(
      baseUrl: baseUrl,
      client: MockClient((_) async => envelope({})),
    );
    await api.post(
      'login.php',
      token: 'top-secret-token',
      body: {'password': 'top-secret-password'},
    );
    final output = messages.join('\n');
    expect(output, contains('POST login.php'));
    expect(output, isNot(contains('top-secret-token')));
    expect(output, isNot(contains('top-secret-password')));
    api.close();
  });

  test('unsafe endpoint paths are rejected as configuration errors', () {
    final api = ApiService(
      baseUrl: baseUrl,
      client: MockClient((_) async => envelope({})),
    );
    expect(
      api.get('https://evil.example/collect'),
      throwsA(
        isA<ApiException>().having(
          (error) => error.type,
          'type',
          ApiErrorType.configurationError,
        ),
      ),
    );
    api.close();
  });
}
