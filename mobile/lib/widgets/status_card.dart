import 'package:flutter/material.dart';

import '../models/grease_trap.dart';
import '../theme/app_theme.dart';
import '../utils/formatters.dart';
import 'sensor_card.dart';

class StatusCard extends StatelessWidget {
  final GreaseTrap trap;
  final bool snapshotExpired;

  const StatusCard({
    super.key,
    required this.trap,
    this.snapshotExpired = false,
  });

  @override
  Widget build(BuildContext context) {
    final reading = trap.reading;
    final stale = trap.isStale || snapshotExpired;
    final status = snapshotExpired ? 'REFRESH NEEDED' : trap.status;
    final urgent = const {
      'CRITICAL',
      'OVERFLOW',
      'EMULSION WARNING',
    }.contains(status);
    final warning = const {'MEDIUM', 'HIGH', 'WARNING'}.contains(status);
    final color = urgent
        ? const Color(0xFF9B2C22)
        : warning
        ? const Color(0xFF805600)
        : stale
        ? const Color(0xFF4C6061)
        : forest;

    return Semantics(
      container: true,
      label: '${trap.name}, condition $status, device ${trap.deviceStatus}',
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Container(
            padding: const EdgeInsets.all(24),
            decoration: BoxDecoration(
              color: color,
              borderRadius: BorderRadius.circular(24),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  trap.name,
                  style: const TextStyle(color: Colors.white, fontSize: 17),
                ),
                const SizedBox(height: 16),
                const Text(
                  'CURRENT CONDITION',
                  style: TextStyle(
                    color: Colors.white,
                    letterSpacing: 1.5,
                    fontSize: 12,
                  ),
                ),
                const SizedBox(height: 8),
                Wrap(
                  crossAxisAlignment: WrapCrossAlignment.center,
                  spacing: 12,
                  children: [
                    Icon(
                      stale
                          ? Icons.cloud_off_outlined
                          : urgent || warning
                          ? Icons.warning_amber_rounded
                          : Icons.check_circle_outline,
                      color: Colors.white,
                      size: 34,
                    ),
                    Text(
                      status,
                      style: const TextStyle(
                        color: Colors.white,
                        fontSize: 28,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 12),
                Text(
                  reading == null
                      ? 'No sensor data has been received yet.'
                      : stale
                      ? 'Device offline. Values below are the last known readings.'
                      : urgent || warning
                      ? 'Attention is required. Inspect the grease trap.'
                      : 'Latest condition reported by the AQUASENSE+ server.',
                  style: const TextStyle(color: Colors.white, fontSize: 16),
                ),
                if (reading?.isTest == true)
                  const Padding(
                    padding: EdgeInsets.only(top: 14),
                    child: Text(
                      'ULTRASONIC TEST · Bench readings only',
                      style: TextStyle(
                        color: Colors.white,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                  ),
                if (reading?.isSimulated == true)
                  const Padding(
                    padding: EdgeInsets.only(top: 14),
                    child: Text(
                      'SIMULATED · Development data',
                      style: TextStyle(
                        color: Colors.white,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                  ),
              ],
            ),
          ),
          const SizedBox(height: 16),
          LayoutBuilder(
            builder: (context, constraints) {
              final cards = [
                SensorCard(
                  icon: Icons.water_drop_outlined,
                  label: stale ? 'Last waste level' : 'Waste level',
                  value: reading == null
                      ? 'Not available'
                      : '${cleanNumber(reading.wasteLevel)}%',
                ),
                SensorCard(
                  icon: Icons.straighten,
                  label: 'Distance to waste surface',
                  value: reading?.ultrasonicDistance == null
                      ? 'Not available'
                      : '${cleanNumber(reading!.ultrasonicDistance!)} cm',
                ),
                SensorCard(
                  icon: Icons.thermostat_outlined,
                  label: stale ? 'Last temperature' : 'Temperature',
                  value: reading?.temperature == null
                      ? 'Not available'
                      : '${cleanNumber(reading!.temperature!)} °C',
                ),
              ];
              if (constraints.maxWidth < 620 ||
                  MediaQuery.textScalerOf(context).scale(1) > 1.4) {
                return Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    for (var index = 0; index < cards.length; index++) ...[
                      cards[index],
                      if (index < cards.length - 1) const SizedBox(height: 12),
                    ],
                  ],
                );
              }
              return Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  for (var index = 0; index < cards.length; index++) ...[
                    Expanded(child: cards[index]),
                    if (index < cards.length - 1) const SizedBox(width: 12),
                  ],
                ],
              );
            },
          ),
          const SizedBox(height: 12),
          Card(
            child: Column(
              children: [
                ListTile(
                  leading: Icon(
                    trap.deviceStatus == 'ONLINE'
                        ? Icons.router_outlined
                        : Icons.portable_wifi_off_outlined,
                    color: trap.deviceStatus == 'ONLINE'
                        ? emerald
                        : const Color(0xFF805600),
                  ),
                  title: Text('Device ${trap.deviceStatus}'),
                  subtitle: Text(trap.deviceCode ?? 'No device assigned'),
                ),
                const Divider(height: 1),
                ListTile(
                  leading: const Icon(Icons.schedule, color: emerald),
                  title: const Text('Last sensor update'),
                  subtitle: Text(
                    reading == null
                        ? 'No sensor data has been received yet.'
                        : readingTime(reading.recordedAt),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
