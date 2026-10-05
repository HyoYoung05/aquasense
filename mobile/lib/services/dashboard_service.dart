import '../models/dashboard_snapshot.dart';
import '../models/establishment.dart';
import '../models/incentive_summary.dart';
import '../models/oil_surrender_summary.dart';
import 'api_service.dart';
import 'auth_service.dart';

class DashboardService {
  final AuthService auth;

  const DashboardService(this.auth);

  Future<DashboardSnapshot> load() async {
    final responses = await Future.wait<Map<String, dynamic>>([
      auth.get('dashboard.php'),
      auth.get('oil-surrenders.php'),
      auth.get('incentives.php'),
    ]);
    try {
      return DashboardSnapshot(
        dashboard: OwnerDashboard.fromJson(responses[0]),
        oilSurrenders: OilSurrenderSummary.fromJson(responses[1]),
        incentives: IncentiveSummary.fromJson(responses[2]),
      );
    } catch (_) {
      throw const ApiException(
        'The server returned unreadable dashboard information.',
        type: ApiErrorType.serverError,
      );
    }
  }
}
