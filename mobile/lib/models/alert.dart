String alertTypeLabel(String code) => switch (code.trim().toUpperCase()) {
  'HIGH_LEVEL' => 'High Waste Level',
  'CRITICAL_LEVEL' => 'Critical Waste Level',
  'OVERFLOW_WARNING' => 'Overflow Warning',
  'OVERFLOW' => 'Overflow',
  'HIGH_TEMPERATURE' => 'High Temperature',
  'EMULSION_WARNING' => 'Emulsion Warning',
  'HIGH_TURBIDITY' => 'High Turbidity',
  'ABNORMAL_FLOW' => 'Abnormal Flow',
  'DEVICE_OFFLINE' => 'Monitoring Device Offline',
  final value when value.isNotEmpty =>
    value
        .split('_')
        .where((part) => part.isNotEmpty)
        .map((part) => '${part[0]}${part.substring(1).toLowerCase()}')
        .join(' '),
  _ => 'Alert',
};

enum AlertStatusFilter {
  unresolved('UNRESOLVED', 'Active'),
  all('ALL', 'All'),
  active('ACTIVE', 'Active only'),
  acknowledged('ACKNOWLEDGED', 'Acknowledged'),
  resolved('RESOLVED', 'Resolved');

  final String apiValue;
  final String label;
  const AlertStatusFilter(this.apiValue, this.label);
}

enum AlertSeverityFilter {
  all('ALL', 'All severities'),
  critical('CRITICAL', 'Critical'),
  warning('WARNING', 'Warning'),
  info('INFO', 'Info');

  final String apiValue;
  final String label;
  const AlertSeverityFilter(this.apiValue, this.label);
}

enum AlertDateRange {
  today('today', 'Today'),
  last7Days('7d', '7 Days'),
  last30Days('30d', '30 Days'),
  custom('custom', 'Custom');

  final String apiValue;
  final String label;
  const AlertDateRange(this.apiValue, this.label);
}

class AlertItem {
  final int id, establishmentId, greaseTrapId, triggerCount;
  final String alertType,
      severity,
      status,
      message,
      label,
      businessName,
      greaseTrapName;
  final String? deviceCode, sensorName;
  final double? sensorValue, thresholdValue;
  final DateTime firstTriggeredAt, lastTriggeredAt;
  final DateTime? acknowledgedAt, resolvedAt;
  const AlertItem({
    required this.id,
    required this.establishmentId,
    required this.greaseTrapId,
    required this.triggerCount,
    required this.alertType,
    required this.severity,
    required this.status,
    required this.message,
    required this.label,
    required this.businessName,
    required this.greaseTrapName,
    required this.deviceCode,
    required this.sensorName,
    required this.sensorValue,
    required this.thresholdValue,
    required this.firstTriggeredAt,
    required this.lastTriggeredAt,
    required this.acknowledgedAt,
    required this.resolvedAt,
  });
  factory AlertItem.fromJson(Map<String, dynamic> json) {
    int integer(String key) {
      final v = json[key];
      if (v is! num) throw const FormatException('Invalid alert.');
      return v.toInt();
    }

    String text(String key, {String fallback = ''}) {
      final v = json[key];
      return v is String && v.trim().isNotEmpty ? v.trim() : fallback;
    }

    DateTime date(String key) {
      final v = DateTime.tryParse(text(key));
      if (v == null) throw const FormatException('Invalid alert time.');
      return v;
    }

    final type = text('alert_type');
    if (type.isEmpty) throw const FormatException('Invalid alert type.');
    return AlertItem(
      id: integer('id'),
      establishmentId: integer('establishment_id'),
      greaseTrapId: integer('grease_trap_id'),
      triggerCount: json['trigger_count'] is num
          ? (json['trigger_count'] as num).toInt()
          : 1,
      alertType: type,
      severity: text('severity', fallback: 'UNKNOWN').toUpperCase(),
      status: text('status', fallback: 'UNKNOWN').toUpperCase(),
      message: text('message', fallback: alertTypeLabel(type)),
      label: text('label', fallback: alertTypeLabel(type)),
      businessName: text(
        'business_name',
        fallback: 'Establishment unavailable',
      ),
      greaseTrapName: text(
        'grease_trap_name',
        fallback: 'Grease trap unavailable',
      ),
      deviceCode: _optionalText(json['device_code']),
      sensorName: _optionalText(json['sensor_name']),
      sensorValue: _number(json['sensor_value']),
      thresholdValue: _number(json['threshold_value']),
      firstTriggeredAt: date('first_triggered_at'),
      lastTriggeredAt: date('last_triggered_at'),
      acknowledgedAt: _date(json['acknowledged_at']),
      resolvedAt: _date(json['resolved_at']),
    );
  }
}

class AlertPreview {
  final int id;
  final String alertType, label, severity, status, message;
  final DateTime lastTriggeredAt;
  const AlertPreview({
    required this.id,
    required this.alertType,
    required this.label,
    required this.severity,
    required this.status,
    required this.message,
    required this.lastTriggeredAt,
  });
  factory AlertPreview.fromJson(Map<String, dynamic> json) {
    final id = json['id'];
    final type = json['alert_type'];
    final at = DateTime.tryParse(json['last_triggered_at'] as String? ?? '');
    if (id is! num || type is! String || at == null) {
      throw const FormatException('Invalid alert preview.');
    }
    return AlertPreview(
      id: id.toInt(),
      alertType: type,
      label: _optionalText(json['label']) ?? alertTypeLabel(type),
      severity: (_optionalText(json['severity']) ?? 'UNKNOWN').toUpperCase(),
      status: (_optionalText(json['status']) ?? 'UNKNOWN').toUpperCase(),
      message: _optionalText(json['message']) ?? alertTypeLabel(type),
      lastTriggeredAt: at,
    );
  }
}

class AlertCounts {
  final int activeCount,
      acknowledgedCount,
      resolvedCount,
      unresolvedCount,
      criticalCount,
      warningCount,
      infoCount;
  const AlertCounts({
    required this.activeCount,
    required this.acknowledgedCount,
    required this.resolvedCount,
    required this.unresolvedCount,
    required this.criticalCount,
    required this.warningCount,
    required this.infoCount,
  });
  factory AlertCounts.fromJson(Map<String, dynamic> json) {
    int n(String k) => json[k] is num ? (json[k] as num).toInt() : 0;
    return AlertCounts(
      activeCount: n('active_count'),
      acknowledgedCount: n('acknowledged_count'),
      resolvedCount: n('resolved_count'),
      unresolvedCount: n('unresolved_count'),
      criticalCount: n('critical_count'),
      warningCount: n('warning_count'),
      infoCount: n('info_count'),
    );
  }
}

class AlertTrap {
  final int id;
  final String name, businessName;
  const AlertTrap({
    required this.id,
    required this.name,
    required this.businessName,
  });
  factory AlertTrap.fromJson(Map<String, dynamic> j) {
    if (j['id'] is! num ||
        j['name'] is! String ||
        j['business_name'] is! String) {
      throw const FormatException('Invalid alert trap.');
    }
    return AlertTrap(
      id: (j['id'] as num).toInt(),
      name: j['name'],
      businessName: j['business_name'],
    );
  }
}

class AlertPage {
  final List<AlertItem> alerts;
  final AlertCounts summary;
  final List<AlertTrap> traps;
  final int page, pages, total;
  const AlertPage({
    required this.alerts,
    required this.summary,
    required this.traps,
    required this.page,
    required this.pages,
    required this.total,
  });
  bool get hasMore => page < pages;
  AlertPage append(AlertPage next) => AlertPage(
    alerts: [...alerts, ...next.alerts],
    summary: next.summary,
    traps: next.traps,
    page: next.page,
    pages: next.pages,
    total: next.total,
  );
  factory AlertPage.fromJson(Map<String, dynamic> j) {
    if (j['alerts'] is! List ||
        j['summary'] is! Map<String, dynamic> ||
        j['traps'] is! List ||
        j['page'] is! num ||
        j['pages'] is! num ||
        j['total'] is! num) {
      throw const FormatException('Invalid alerts response.');
    }
    return AlertPage(
      alerts: (j['alerts'] as List)
          .map((v) => AlertItem.fromJson(v as Map<String, dynamic>))
          .toList(),
      summary: AlertCounts.fromJson(j['summary']),
      traps: (j['traps'] as List)
          .map((v) => AlertTrap.fromJson(v as Map<String, dynamic>))
          .toList(),
      page: (j['page'] as num).toInt(),
      pages: (j['pages'] as num).toInt(),
      total: (j['total'] as num).toInt(),
    );
  }
}

String? _optionalText(Object? v) =>
    v is String && v.trim().isNotEmpty ? v.trim() : null;
double? _number(Object? v) {
  if (v == null) return null;
  if (v is num) return v.toDouble();
  throw const FormatException('Invalid alert value.');
}

DateTime? _date(Object? v) {
  if (v == null) return null;
  if (v is! String) throw const FormatException('Invalid alert timestamp.');
  final d = DateTime.tryParse(v);
  if (d == null) throw const FormatException('Invalid alert timestamp.');
  return d;
}
