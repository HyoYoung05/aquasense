class SensorReading {
  final double wasteLevel;
  final double? temperature, ultrasonicDistance;
  final bool isSimulated, isTest;
  final DateTime recordedAt;
  const SensorReading({
    required this.wasteLevel,
    required this.temperature,
    required this.isSimulated,
    this.isTest = false,
    this.ultrasonicDistance,
    required this.recordedAt,
  });
  factory SensorReading.fromJson(Map<String, dynamic> json) {
    final wasteLevel = json['waste_level_percent'];
    final temperature = json['temperature_c'];
    final distance = json['ultrasonic_distance_cm'];
    final isSimulated = json['is_simulated'];
    final isTest = json['is_test'];
    final recordedAt = json['recorded_at'];
    if (wasteLevel is! num ||
        (temperature != null && temperature is! num) ||
        (distance != null && distance is! num) ||
        isSimulated is! bool ||
        (isTest != null && isTest is! bool) ||
        recordedAt is! String) {
      throw const FormatException('Invalid sensor reading.');
    }
    final parsedTime = DateTime.tryParse(recordedAt);
    if (parsedTime == null) {
      throw const FormatException('Invalid sensor reading time.');
    }
    return SensorReading(
      wasteLevel: wasteLevel.toDouble(),
      temperature: (temperature as num?)?.toDouble(),
      isSimulated: isSimulated,
      isTest: isTest as bool? ?? false,
      ultrasonicDistance: (distance as num?)?.toDouble(),
      recordedAt: parsedTime,
    );
  }
}
