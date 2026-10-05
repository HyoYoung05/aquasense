import 'alert.dart';

class OwnerAlert {
  final int id;
  final String type;
  final String severity;
  final String message;
  final String status;
  final String label;
  final String greaseTrapName;
  final DateTime lastTriggeredAt;

  const OwnerAlert({
    required this.id,
    required this.type,
    required this.severity,
    required this.message,
    required this.status,
    required this.label,
    required this.greaseTrapName,
    required this.lastTriggeredAt,
  });

  factory OwnerAlert.fromJson(Map<String, dynamic> json) {
    final id = json['id'];
    final type = json['alert_type'];
    final severity = json['severity'];
    final message = json['message'];
    final status = json['status'];
    final label = json['label'];
    final trapName = json['grease_trap_name'];
    final triggeredAt = json['last_triggered_at'];
    if (id is! num ||
        type is! String ||
        severity is! String ||
        status is! String ||
        trapName is! String ||
        triggeredAt is! String) {
      throw const FormatException('Invalid alert summary.');
    }
    final parsedTime = DateTime.tryParse(
      triggeredAt.contains('T')
          ? triggeredAt
          : '${triggeredAt.replaceFirst(' ', 'T')}Z',
    );
    if (parsedTime == null) throw const FormatException('Invalid alert time.');
    return OwnerAlert(
      id: id.toInt(),
      type: type,
      severity: severity,
      message: message is String && message.trim().isNotEmpty
          ? message.trim()
          : alertTypeLabel(type),
      status: status,
      label: label is String && label.trim().isNotEmpty
          ? label.trim()
          : alertTypeLabel(type),
      greaseTrapName: trapName,
      lastTriggeredAt: parsedTime,
    );
  }
}
