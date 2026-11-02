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
    final offline = trap.deviceStatus != 'ONLINE';
    final stale = trap.isStale || snapshotExpired;
    final status = offline ? 'OFFLINE' : 'ONLINE';
    final statusColor = offline ? offlineStatus : onlineStatus;

    return Semantics(
      container: true,
      label: '${trap.name}, device $status',
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Container(
            padding: const EdgeInsets.all(22),
            decoration: BoxDecoration(
              color: statusColor.withValues(alpha: .08),
              border: Border.all(color: statusColor.withValues(alpha: .28)),
              borderRadius: BorderRadius.circular(14),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  trap.name,
                  style: const TextStyle(color: ink, fontSize: 17),
                ),
                const SizedBox(height: 7),
                const Row(
                  children: [
                    Icon(
                      Icons.verified_user_outlined,
                      color: emerald,
                      size: 17,
                    ),
                    SizedBox(width: 7),
                    Expanded(
                      child: Text(
                        'Assigned to your owner account',
                        key: ValueKey('owner-assignment-label'),
                        style: TextStyle(
                          color: emerald,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 16),
                const Text(
                  'DEVICE STATUS',
                  style: TextStyle(
                    color: muted,
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
                      offline
                          ? Icons.cloud_off_outlined
                          : Icons.check_circle_outline,
                      color: statusColor,
                      size: 34,
                    ),
                    Text(
                      status,
                      key: const ValueKey('device-status-headline'),
                      style: TextStyle(
                        color: statusColor,
                        fontSize: 27,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ],
                ),
                if (reading?.isTest == true)
                  const Padding(
                    padding: EdgeInsets.only(top: 14),
                    child: Text(
                      'TEST DATA · Development reading',
                      style: TextStyle(color: ink, fontWeight: FontWeight.bold),
                    ),
                  ),
                if (reading?.isSimulated == true)
                  const Padding(
                    padding: EdgeInsets.only(top: 14),
                    child: Text(
                      'SIMULATED · Development data',
                      style: TextStyle(color: ink, fontWeight: FontWeight.bold),
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
                        ? onlineStatus
                        : offlineStatus,
                  ),
                  title: Text(
                    'Device ${trap.deviceStatus}',
                    key: const ValueKey('device-status-detail'),
                    style: TextStyle(
                      color: trap.deviceStatus == 'ONLINE'
                          ? onlineStatus
                          : offlineStatus,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
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
