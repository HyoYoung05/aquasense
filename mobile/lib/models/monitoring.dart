import 'alert.dart';

enum HistoryRange {
  lastHour('1h', 'Last Hour'),
  today('today', 'Today'),
  last24Hours('24h', '24 Hours'),
  last7Days('7d', '7 Days'),
  last30Days('30d', '30 Days'),
  custom('custom', 'Custom');

  final String apiValue;
  final String label;
  const HistoryRange(this.apiValue, this.label);
}

enum SensorMetric {
  wasteLevel('Waste Level', '%'),
  ultrasonicDistance('Distance', 'cm'),
  temperature('Temperature', '°C'),
  turbidity('Turbidity', 'NTU'),
  flowRate('Flow', 'L/min'),
  gas('Gas Sensor', 'raw');

  final String label;
  final String unit;
  const SensorMetric(this.label, this.unit);

  double? valueOf(TelemetryPoint point) => switch (this) {
    SensorMetric.wasteLevel => point.wasteLevel,
    SensorMetric.ultrasonicDistance => point.ultrasonicDistance,
    SensorMetric.temperature => point.temperature,
    SensorMetric.turbidity => point.turbidity,
    SensorMetric.flowRate => point.flowRate,
    SensorMetric.gas => point.gasValue,
  };
}

class MonitoringReading {
  final double? wasteLevel;
  final double? ultrasonicDistance;
  final double? temperature;
  final double? turbidity;
  final double? flowRate;
  final double? gasValue;
  final String condition;
  final bool isSimulated;
  final bool isTest;
  final DateTime recordedAt;

  const MonitoringReading({
    required this.wasteLevel,
    required this.ultrasonicDistance,
    required this.temperature,
    required this.turbidity,
    required this.flowRate,
    required this.gasValue,
    required this.condition,
    required this.isSimulated,
    required this.isTest,
    required this.recordedAt,
  });

  factory MonitoringReading.fromJson(Map<String, dynamic> json) {
    final recordedAt = DateTime.tryParse(json['recorded_at'] as String? ?? '');
    if (recordedAt == null ||
        json['condition'] is! String ||
        json['is_simulated'] is! bool ||
        json['is_test'] is! bool) {
      throw const FormatException('Invalid monitoring reading.');
    }
    return MonitoringReading(
      wasteLevel: _number(json['waste_level_percent']),
      ultrasonicDistance: _number(json['ultrasonic_distance_cm']),
      temperature: _number(json['temperature_c']),
      turbidity: _number(json['turbidity_ntu']),
      flowRate: _number(json['flow_rate_lpm']),
      gasValue: _number(json['gas_value']),
      condition: (json['condition'] as String).trim(),
      isSimulated: json['is_simulated'] as bool,
      isTest: json['is_test'] as bool,
      recordedAt: recordedAt,
    );
  }
}

class GreaseTrapMonitoring {
  final int establishmentId;
  final String businessName;
  final int greaseTrapId;
  final String greaseTrapName;
  final String? deviceCode;
  final String? deviceName;
  final String? firmwareVersion;
  final String deviceStatus;
  final DateTime? lastSeenAt;
  final String sensorState;
  final bool isStale;
  final MonitoringReading? reading;
  final AlertPreview? activeAlert;

  const GreaseTrapMonitoring({
    required this.establishmentId,
    required this.businessName,
    required this.greaseTrapId,
    required this.greaseTrapName,
    required this.deviceCode,
    required this.deviceName,
    required this.firmwareVersion,
    required this.deviceStatus,
    required this.lastSeenAt,
    required this.sensorState,
    required this.isStale,
    required this.reading,
    this.activeAlert,
  });

  factory GreaseTrapMonitoring.fromJson(Map<String, dynamic> json) {
    final establishmentId = json['establishment_id'];
    final trapId = json['grease_trap_id'];
    final businessName = json['business_name'];
    final trapName = json['grease_trap_name'];
    final deviceStatus = json['device_status'];
    final sensorState = json['sensor_state'];
    final isStale = json['is_stale'];
    final reading = json['reading'];
    if (establishmentId is! num ||
        trapId is! num ||
        businessName is! String ||
        trapName is! String ||
        deviceStatus is! String ||
        sensorState is! String ||
        isStale is! bool ||
        (reading != null && reading is! Map<String, dynamic>)) {
      throw const FormatException('Invalid monitoring trap.');
    }
    return GreaseTrapMonitoring(
      establishmentId: establishmentId.toInt(),
      businessName: businessName,
      greaseTrapId: trapId.toInt(),
      greaseTrapName: trapName,
      deviceCode: _optionalString(json['device_code']),
      deviceName: _optionalString(json['device_name']),
      firmwareVersion: _optionalString(json['firmware_version']),
      deviceStatus: deviceStatus,
      lastSeenAt: _optionalDate(json['last_seen_at']),
      sensorState: sensorState,
      isStale: isStale,
      reading: reading == null ? null : MonitoringReading.fromJson(reading),
      activeAlert: json['active_alert'] == null
          ? null
          : AlertPreview.fromJson(json['active_alert'] as Map<String, dynamic>),
    );
  }
}

class MonitoringSnapshot {
  final DateTime generatedAt;
  final Duration freshness;
  final List<GreaseTrapMonitoring> traps;

  const MonitoringSnapshot({
    required this.generatedAt,
    required this.freshness,
    required this.traps,
  });

  factory MonitoringSnapshot.fromJson(Map<String, dynamic> json) {
    final generatedAt = DateTime.tryParse(
      json['generated_at'] as String? ?? '',
    );
    final freshness = json['freshness_seconds'];
    final values = json['traps'];
    if (generatedAt == null || freshness is! num || values is! List) {
      throw const FormatException('Invalid monitoring response.');
    }
    return MonitoringSnapshot(
      generatedAt: generatedAt,
      freshness: Duration(seconds: freshness.toInt()),
      traps: values
          .map((value) {
            if (value is! Map<String, dynamic>) {
              throw const FormatException('Invalid monitoring trap list.');
            }
            return GreaseTrapMonitoring.fromJson(value);
          })
          .toList(growable: false),
    );
  }
}

class TelemetryPoint {
  final String? deviceCode;
  final double? wasteLevel;
  final double? ultrasonicDistance;
  final double? temperature;
  final double? turbidity;
  final double? flowRate;
  final double? gasValue;
  final String condition;
  final bool isSimulated;
  final bool isTest;
  final DateTime recordedAt;

  const TelemetryPoint({
    required this.deviceCode,
    required this.wasteLevel,
    required this.ultrasonicDistance,
    required this.temperature,
    required this.turbidity,
    required this.flowRate,
    required this.gasValue,
    required this.condition,
    required this.isSimulated,
    required this.isTest,
    required this.recordedAt,
  });

  factory TelemetryPoint.fromJson(Map<String, dynamic> json) {
    final recordedAt = DateTime.tryParse(json['recorded_at'] as String? ?? '');
    final condition = json['level_status'];
    if (recordedAt == null ||
        condition is! String ||
        json['is_simulated'] is! bool ||
        json['is_test'] is! bool) {
      throw const FormatException('Invalid telemetry point.');
    }
    return TelemetryPoint(
      deviceCode: _optionalString(json['device_code']),
      wasteLevel: _number(json['waste_level_percent']),
      ultrasonicDistance: _number(json['ultrasonic_distance_cm']),
      temperature: _number(json['temperature_c']),
      turbidity: _number(json['turbidity_ntu']),
      flowRate: _number(json['flow_rate_lpm']),
      gasValue: _number(json['gas_value']),
      condition: condition,
      isSimulated: json['is_simulated'] as bool,
      isTest: json['is_test'] as bool,
      recordedAt: recordedAt,
    );
  }
}

class TelemetryHistory {
  final int trapId;
  final String trapName;
  final String businessName;
  final HistoryRange range;
  final DateTime from;
  final DateTime to;
  final int page;
  final int pages;
  final int total;
  final List<TelemetryPoint> readings;

  const TelemetryHistory({
    required this.trapId,
    required this.trapName,
    required this.businessName,
    required this.range,
    required this.from,
    required this.to,
    required this.page,
    required this.pages,
    required this.total,
    required this.readings,
  });

  bool get hasMore => page < pages;

  TelemetryHistory append(TelemetryHistory next) => TelemetryHistory(
    trapId: trapId,
    trapName: trapName,
    businessName: businessName,
    range: range,
    from: from,
    to: to,
    page: next.page,
    pages: next.pages,
    total: next.total,
    readings: [...readings, ...next.readings],
  );

  factory TelemetryHistory.fromJson(Map<String, dynamic> json) {
    final trap = json['trap'];
    final rawRange = json['range'];
    final from = DateTime.tryParse(json['from'] as String? ?? '');
    final to = DateTime.tryParse(json['to'] as String? ?? '');
    final page = json['page'];
    final pages = json['pages'];
    final total = json['total'];
    final values = json['readings'];
    if (trap is! Map<String, dynamic> ||
        rawRange is! String ||
        from == null ||
        to == null ||
        page is! num ||
        pages is! num ||
        total is! num ||
        values is! List ||
        trap['id'] is! num ||
        trap['name'] is! String ||
        trap['business_name'] is! String) {
      throw const FormatException('Invalid telemetry history.');
    }
    final range = HistoryRange.values.firstWhere(
      (value) => value.apiValue == rawRange,
      orElse: () => HistoryRange.today,
    );
    return TelemetryHistory(
      trapId: (trap['id'] as num).toInt(),
      trapName: trap['name'] as String,
      businessName: trap['business_name'] as String,
      range: range,
      from: from,
      to: to,
      page: page.toInt(),
      pages: pages.toInt(),
      total: total.toInt(),
      readings: values
          .map((value) {
            if (value is! Map<String, dynamic>) {
              throw const FormatException('Invalid telemetry history list.');
            }
            return TelemetryPoint.fromJson(value);
          })
          .toList(growable: false),
    );
  }
}

double? _number(Object? value) {
  if (value == null) return null;
  if (value is! num) throw const FormatException('Invalid sensor value.');
  return value.toDouble();
}

String? _optionalString(Object? value) {
  if (value == null) return null;
  if (value is! String) throw const FormatException('Invalid text value.');
  final trimmed = value.trim();
  return trimmed.isEmpty ? null : trimmed;
}

DateTime? _optionalDate(Object? value) {
  if (value == null) return null;
  if (value is! String) throw const FormatException('Invalid timestamp.');
  final parsed = DateTime.tryParse(value);
  if (parsed == null) throw const FormatException('Invalid timestamp.');
  return parsed;
}
