import '../models/owner_context.dart';
import 'api_service.dart';
import 'auth_service.dart';

class OwnerContextService {
  final AuthService auth;

  const OwnerContextService(this.auth);

  Future<OwnerContext> load() async {
    final data = await auth.get('dashboard.php');
    try {
      return OwnerContext.fromJson(data);
    } catch (_) {
      throw const ApiException(
        'The server returned unreadable owner information.',
        type: ApiErrorType.serverError,
      );
    }
  }
}
