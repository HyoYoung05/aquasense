import 'package:aquasense_mobile/config/api_config.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test(
    'release HTTP configuration gives an actionable error before networking',
    () {
      expect(
        () => ApiConfig.validatedEndpoint(
          'http://10.0.2.2/AQUASENSE+/aquasense-web/api/mobile',
          'login.php',
          releaseMode: true,
        ),
        throwsA(
          isA<ApiConfigurationException>().having(
            (e) => e.message,
            'message',
            contains('debug builds'),
          ),
        ),
      );
    },
  );
  test('local-testing builds allow the explicitly configured LAN address', () {
    expect(
      ApiConfig.validatedEndpoint(
        'http://192.168.1.20/api/mobile/',
        'login.php',
        releaseMode: false,
      ).toString(),
      'http://192.168.1.20/api/mobile/login.php',
    );
  });
  test(
    'release HTTPS is accepted and malformed configuration is distinguished',
    () {
      expect(
        ApiConfig.validatedEndpoint(
          'https://example.test/api/mobile',
          'login.php',
          releaseMode: true,
        ).scheme,
        'https',
      );
      for (final address in [
        'not-a-url',
        'http://',
        'https://user:password@example.test/api/mobile',
        'http://localhost/api?wrong=true',
        'https://example.test/api/mobile#wrong',
      ]) {
        expect(
          () => ApiConfig.validatedEndpoint(
            address,
            'login.php',
            releaseMode: false,
          ),
          throwsA(isA<ApiConfigurationException>()),
        );
      }
    },
  );
  test('an API URL is required on every platform', () {
    expect(
      () => ApiConfig.resolveBaseUrl(),
      throwsA(
        isA<ApiConfigurationException>().having(
          (e) => e.message,
          'message',
          contains('API_BASE_URL'),
        ),
      ),
    );
  });
  test('explicit development or production URL is used unchanged', () {
    for (final url in [
      'http://development-host.test/api/mobile',
      'https://example.test/api/mobile',
    ]) {
      expect(ApiConfig.resolveBaseUrl(override: url), url);
    }
  });

  test('trailing slashes normalize and endpoint traversal is rejected', () {
    expect(
      ApiConfig.validatedEndpoint(
        'https://example.test/api/mobile///',
        'profile.php',
        releaseMode: true,
      ).toString(),
      'https://example.test/api/mobile/profile.php',
    );
    for (final path in [
      'https://evil.example/profile.php',
      '../profile.php',
      'profile.php#fragment',
    ]) {
      expect(
        () => ApiConfig.validatedEndpoint(
          'https://example.test/api/mobile',
          path,
          releaseMode: true,
        ),
        throwsA(isA<ApiConfigurationException>()),
      );
    }
  });
}
