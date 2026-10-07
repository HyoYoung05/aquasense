import '../models/incentive_summary.dart';
import 'api_service.dart';
import 'auth_service.dart';

abstract class IncentivesRepository {
  Future<IncentiveSummary> load();
}

class IncentivesService implements IncentivesRepository {
  final AuthService auth;
  const IncentivesService(this.auth);

  @override
  Future<IncentiveSummary> load() async {
    try {
      return IncentiveSummary.fromJson(await auth.get('incentives.php'));
    } on FormatException {
      throw const ApiException(
        'The server returned unreadable incentive information.',
        type: ApiErrorType.serverError,
      );
    }
  }
}
