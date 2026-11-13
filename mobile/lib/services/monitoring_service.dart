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
      'page_size': '10',
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
      Future<Map<String, dynamic>> fetchPage(int serverPage) => auth.get(
        Uri(
          path: 'telemetry-history.php',
          queryParameters: {...query, 'page': serverPage.toString()},
        ).toString(),
      );
      var data = await fetchPage(page);
      // Earlier PHP deployments return 50 rows without page-size metadata.
      final serverPageSize = (data['page_size'] as num?)?.toInt() ?? 50;
      final total = (data['total'] as num).toInt();
      final pages = total == 0 ? 1 : (total / 10).ceil();
      final currentPage = page.clamp(1, pages);
      final firstReading = (currentPage - 1) * 10;
      final serverPage = firstReading ~/ serverPageSize + 1;
      if (data['page'] != serverPage) {
        data = await fetchPage(serverPage);
      }
      final readings = data['readings'] as List<dynamic>;
      final offset = firstReading % serverPageSize;
      return TelemetryHistory.fromJson({
        ...data,
        'page': currentPage,
        'pages': pages,
        'readings': readings.skip(offset).take(10).toList(),
      });
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
