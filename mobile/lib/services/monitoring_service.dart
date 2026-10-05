import '../models/monitoring.dart';
import 'api_service.dart';
import 'auth_service.dart';

abstract class MonitoringRepository {
  Future<MonitoringSnapshot> loadCurrent();
  Future<TelemetryHistory> loadHistory({
    required int greaseTrapId,
    required HistoryRange range,
    DateTime? from,
    DateTime? to,
    int page = 1,
  });
}

class MonitoringService implements MonitoringRepository {
  final AuthService auth;

  const MonitoringService(this.auth);

  @override
  Future<MonitoringSnapshot> loadCurrent() async {
    try {
      return MonitoringSnapshot.fromJson(await auth.get('monitoring.php'));
    } on FormatException {
      throw const ApiException(
        'The server returned unreadable monitoring information.',
        type: ApiErrorType.serverError,
      );
    }
  }

  @override
  Future<TelemetryHistory> loadHistory({
    required int greaseTrapId,
    required HistoryRange range,
    DateTime? from,
    DateTime? to,
    int page = 1,
  }) async {
    final query = <String, String>{
      'grease_trap_id': greaseTrapId.toString(),
      'range': range.apiValue,
      'page': page.toString(),
    };
    if (range == HistoryRange.custom) {
      if (from == null || to == null) {
        throw const ApiException(
          'Choose a start and end date.',
          type: ApiErrorType.validationError,
        );
      }
      query['from'] = _date(from);
      query['to'] = _date(to);
    }
    try {
      final path = Uri(
        path: 'telemetry-history.php',
        queryParameters: query,
      ).toString();
      return TelemetryHistory.fromJson(await auth.get(path));
    } on FormatException {
      throw const ApiException(
        'The server returned unreadable telemetry history.',
        type: ApiErrorType.serverError,
      );
    }
  }

  static String _date(DateTime date) =>
      '${date.year.toString().padLeft(4, '0')}-'
      '${date.month.toString().padLeft(2, '0')}-'
      '${date.day.toString().padLeft(2, '0')}';
}
