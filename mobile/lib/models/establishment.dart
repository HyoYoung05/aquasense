import 'alert_summary.dart';
import 'grease_trap.dart';
import 'user.dart';

class Establishment {
  final int id;
  final String businessName;
  final List<GreaseTrap> traps;

  const Establishment({
    required this.id,
    required this.businessName,
    required this.traps,
  });

  factory Establishment.fromJson(Map<String, dynamic> json) {
    final id = json['id'];
    final name = json['business_name'];
    final trapValues = json['traps'];
    if (id is! num ||
        name is! String ||
        name.trim().isEmpty ||
        trapValues is! List) {
      throw const FormatException('Invalid establishment summary.');
    }
    final traps = <GreaseTrap>[];
    for (final value in trapValues) {
      if (value is! Map<String, dynamic>) {
        throw const FormatException('Invalid grease trap list.');
      }
      traps.add(GreaseTrap.fromJson(value));
    }
    return Establishment(
      id: id.toInt(),
      businessName: name.trim(),
      traps: traps,
    );
  }
}

class OwnerDashboard {
  final OwnerUser user;
  final List<Establishment> establishments;
  final List<OwnerAlert> alerts;
  final DateTime generatedAt;
  final Duration freshness;

  const OwnerDashboard({
    required this.user,
    required this.establishments,
    required this.alerts,
    required this.generatedAt,
    required this.freshness,
  });

  factory OwnerDashboard.fromJson(Map<String, dynamic> json) {
    final user = json['user'];
    final establishmentValues = json['establishments'];
    final alertValues = json['alerts'];
    final generatedAt = json['generated_at'];
    final freshness = json['freshness_seconds'];
    if (user is! Map<String, dynamic> ||
        establishmentValues is! List ||
        alertValues is! List ||
        generatedAt is! String ||
        freshness is! num) {
      throw const FormatException('Invalid owner dashboard.');
    }
    final establishments = <Establishment>[];
    for (final value in establishmentValues) {
      if (value is! Map<String, dynamic>) {
        throw const FormatException('Invalid establishment list.');
      }
      establishments.add(Establishment.fromJson(value));
    }
    final alerts = <OwnerAlert>[];
    for (final value in alertValues) {
      if (value is! Map<String, dynamic>) {
        throw const FormatException('Invalid alert list.');
      }
      alerts.add(OwnerAlert.fromJson(value));
    }
    final generated = DateTime.tryParse(generatedAt);
    if (generated == null) {
      throw const FormatException('Invalid dashboard timestamp.');
    }
    return OwnerDashboard(
      user: OwnerUser.fromJson(user),
      establishments: establishments,
      alerts: alerts,
      generatedAt: generated,
      freshness: Duration(seconds: freshness.toInt()),
    );
  }
}
