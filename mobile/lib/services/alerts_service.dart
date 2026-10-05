import '../models/alert.dart';
import 'api_service.dart';
import 'auth_service.dart';

abstract class AlertsRepository {
  Future<AlertPage> loadAlerts({
    required AlertStatusFilter status,
    required AlertSeverityFilter severity,
    required AlertDateRange range,
    DateTime? from,
    DateTime? to,
    int? greaseTrapId,
    int page = 1,
  });
  Future<AlertItem> loadAlert(int id);
}

class AlertsService implements AlertsRepository {
  final AuthService auth;
  const AlertsService(this.auth);
  @override
  Future<AlertPage> loadAlerts({
    required AlertStatusFilter status,
    required AlertSeverityFilter severity,
    required AlertDateRange range,
    DateTime? from,
    DateTime? to,
    int? greaseTrapId,
    int page = 1,
  }) async {
    final q = {
      'status': status.apiValue,
      'severity': severity.apiValue,
      'range': range.apiValue,
      'page': '$page',
    };
    if (greaseTrapId != null) q['grease_trap_id'] = '$greaseTrapId';
    if (range == AlertDateRange.custom) {
      if (from == null || to == null) {
        throw const ApiException(
          'Choose a start and end date.',
          type: ApiErrorType.validationError,
        );
      }
      q['from'] = _date(from);
      q['to'] = _date(to);
    }
    try {
      return AlertPage.fromJson(
        await auth.get(Uri(path: 'alerts.php', queryParameters: q).toString()),
      );
    } on FormatException {
      throw const ApiException(
        'The server returned unreadable alert information.',
        type: ApiErrorType.serverError,
      );
    }
  }

  @override
  Future<AlertItem> loadAlert(int id) async {
    try {
      final data = await auth.get('alert.php?id=$id');
      return AlertItem.fromJson(data['alert'] as Map<String, dynamic>);
    } on FormatException {
      throw const ApiException(
        'The server returned unreadable alert information.',
        type: ApiErrorType.serverError,
      );
    }
  }

  String _date(DateTime d) =>
      '${d.year.toString().padLeft(4, '0')}-${d.month.toString().padLeft(2, '0')}-${d.day.toString().padLeft(2, '0')}';
}
