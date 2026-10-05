import 'user.dart';

class AuthResult {
  final String token;
  final DateTime? expiresAt;
  final OwnerUser user;

  const AuthResult({
    required this.token,
    required this.expiresAt,
    required this.user,
  });

  factory AuthResult.fromJson(Map<String, dynamic> json) {
    final token = json['token'];
    final user = json['user'];
    final expires = json['expires_at'];
    if (token is! String || token.isEmpty || user is! Map<String, dynamic>) {
      throw const FormatException('Invalid authentication result.');
    }
    return AuthResult(
      token: token,
      expiresAt: expires is String ? DateTime.tryParse(expires) : null,
      user: OwnerUser.fromJson(user),
    );
  }
}
