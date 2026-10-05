import 'sensor_reading.dart';

class GreaseTrap {
  final int id;
  final String name, status, deviceStatus;
  final String? deviceCode;
  final bool isStale;
  final SensorReading? reading;
  const GreaseTrap({
    required this.id,
    required this.name,
    required this.status,
    required this.deviceStatus,
    required this.deviceCode,
    required this.isStale,
    this.reading,
  });
  factory GreaseTrap.fromJson(Map<String, dynamic> json) {
    final id = json['id'];
    final name = json['name'];
    final status = json['status'];
    final deviceStatus = json['device_status'];
    final deviceCode = json['device_code'];
    final isStale = json['is_stale'];
    final readingValue = json['reading'];
    if (id is! num ||
        name is! String ||
        status is! String ||
        deviceStatus is! String ||
        (deviceCode != null && deviceCode is! String) ||
        isStale is! bool ||
        (readingValue != null && readingValue is! Map<String, dynamic>)) {
      throw const FormatException('Invalid grease trap summary.');
    }
    return GreaseTrap(
      id: id.toInt(),
      name: name,
      status: status,
      deviceStatus: deviceStatus,
      deviceCode: deviceCode as String?,
      isStale: isStale,
      reading: readingValue == null
          ? null
          : SensorReading.fromJson(readingValue),
    );
  }
}
