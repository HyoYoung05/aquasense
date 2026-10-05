import 'package:aquasense_mobile/models/grease_trap.dart';
import 'package:aquasense_mobile/models/sensor_reading.dart';
import 'package:aquasense_mobile/widgets/status_card.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  Map<String, dynamic> payload() => {
    'waste_level_percent': 75,
    'temperature_c': null,
    'ultrasonic_distance_cm': 11.25,
    'is_simulated': false,
    'is_test': true,
    'recorded_at': '2026-09-21T00:00:00Z',
  };

  test(
    'ultrasonic-only telemetry retains absent temperature and test identity',
    () {
      final reading = SensorReading.fromJson(payload());
      expect(reading.temperature, isNull);
      expect(reading.ultrasonicDistance, 11.25);
      expect(reading.wasteLevel, 75);
      expect(reading.isTest, isTrue);
      expect(reading.isSimulated, isFalse);
    },
  );

  test('older API readings remain compatible without test metadata', () {
    final json = payload()
      ..remove('is_test')
      ..remove('ultrasonic_distance_cm');
    json['temperature_c'] = 30.4;
    final reading = SensorReading.fromJson(json);
    expect(reading.temperature, 30.4);
    expect(reading.isTest, isFalse);
    expect(reading.ultrasonicDistance, isNull);
  });

  testWidgets('test warning renders without inventing a temperature', (
    tester,
  ) async {
    final trap = GreaseTrap(
      id: 12,
      name: 'Ultrasonic bench test',
      status: 'WARNING',
      deviceStatus: 'ONLINE',
      deviceCode: 'AQS-001',
      isStale: false,
      reading: SensorReading.fromJson(payload()),
    );
    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: SingleChildScrollView(
            child: StatusCard(trap: trap, snapshotExpired: false),
          ),
        ),
      ),
    );
    expect(find.text('WARNING'), findsOneWidget);
    expect(find.text('75%'), findsOneWidget);
    expect(find.text('ULTRASONIC TEST · Bench readings only'), findsOneWidget);
    expect(find.text('11.3 cm'), findsOneWidget);
    expect(find.text('Not available'), findsOneWidget);
    expect(find.textContaining('0.0 °C'), findsNothing);
    expect(tester.takeException(), isNull);
  });
}
