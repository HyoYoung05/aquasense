import 'establishment.dart';
import 'incentive_summary.dart';
import 'oil_surrender_summary.dart';

class DashboardSnapshot {
  final OwnerDashboard dashboard;
  final OilSurrenderSummary oilSurrenders;
  final IncentiveSummary incentives;

  const DashboardSnapshot({
    required this.dashboard,
    required this.oilSurrenders,
    required this.incentives,
  });
}
