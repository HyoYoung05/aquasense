import 'package:aquasense_mobile/models/auth_result.dart';
import 'package:aquasense_mobile/models/owner_context.dart';
import 'package:aquasense_mobile/models/user.dart';
import 'package:aquasense_mobile/services/auth_service.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  const userJson = {
    'id': 7,
    'full_name': '  Alex Santos  ',
    'email': ' owner@example.test ',
  };

  test('owner profile parses actual fields and ignores optional extras', () {
    final profile = OwnerUser.fromJson({
      ...userJson,
      'contact_number': null,
      'account_status': null,
    });
    expect(profile.id, 7);
    expect(profile.fullName, 'Alex Santos');
    expect(profile.email, 'owner@example.test');
  });

  test('authentication result is typed and tolerates absent expiry', () {
    final result = AuthResult.fromJson({'token': 'a' * 64, 'user': userJson});
    expect(result.token, 'a' * 64);
    expect(result.expiresAt, isNull);
    expect(result.user.id, 7);
  });

  test('owner context uses server-assigned establishments only', () {
    final context = OwnerContext.fromJson({
      'user': userJson,
      'establishments': [
        {'id': 12, 'business_name': '  Sample Kitchen  ', 'traps': <Object>[]},
      ],
      'generated_at': '2026-10-03T00:00:00Z',
      'freshness_seconds': 600,
    });
    expect(context.establishments.single.id, 12);
    expect(context.establishments.single.businessName, 'Sample Kitchen');
  });

  test('token storage keys are isolated by validated backend URL', () {
    final development = SecureTokenStore.keyForServer(
      'https://dev.example.test/api/mobile',
    );
    final production = SecureTokenStore.keyForServer(
      'https://production.example.test/api/mobile',
    );
    expect(development, isNot(production));
    expect(development, startsWith('aquasense_owner_token:'));
    expect(production, startsWith('aquasense_owner_token:'));
  });
}
