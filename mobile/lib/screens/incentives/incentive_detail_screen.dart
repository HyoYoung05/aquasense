import 'package:flutter/material.dart';

import '../../models/incentive_summary.dart';
import '../../theme/app_theme.dart';
import '../../utils/formatters.dart';

class IncentiveDetailScreen extends StatelessWidget {
  final IncentiveTransaction transaction;

  const IncentiveDetailScreen({super.key, required this.transaction});

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Incentive Details')),
    body: ListView(
      padding: const EdgeInsets.fromLTRB(16, 8, 16, 32),
      children: [
        Center(
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 760),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Card(
                  color: forest,
                  child: Padding(
                    padding: const EdgeInsets.all(20),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        IncentiveStatusChip(status: transaction.status),
                        const SizedBox(height: 12),
                        Text(
                          transaction.transactionCode,
                          style: const TextStyle(
                            color: Colors.white,
                            fontSize: 22,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                        const SizedBox(height: 6),
                        Text(
                          incentiveStatusMessage(transaction.status),
                          style: const TextStyle(color: Colors.white70),
                        ),
                      ],
                    ),
                  ),
                ),
                const SizedBox(height: 12),
                _Section(
                  title: 'Rice incentive',
                  children: [
                    _Detail(
                      'Reward',
                      '${cleanQuantity(transaction.rewardQuantity)} ${transaction.rewardUnit}',
                    ),
                    _Detail('Status', incentiveStatusLabel(transaction.status)),
                    _Detail('Processed', readingTime(transaction.processedAt)),
                  ],
                ),
                const SizedBox(height: 12),
                _Section(
                  title: 'Related oil surrender',
                  children: [
                    _Detail(
                      'Surrender code',
                      transaction.surrenderCode ?? 'Not available',
                    ),
                    _Detail(
                      'Oil quantity',
                      transaction.oilQuantity == null ||
                              transaction.oilUnit == null
                          ? 'Not available'
                          : '${cleanQuantity(transaction.oilQuantity!)} ${transaction.oilUnit}',
                    ),
                    if (transaction.businessName != null)
                      _Detail('Business', transaction.businessName!),
                  ],
                ),
                const SizedBox(height: 12),
                _Section(
                  title: 'Distribution',
                  children: [
                    _Detail(
                      'Distribution status',
                      incentiveStatusLabel(transaction.status),
                    ),
                    _Detail(
                      'Distribution date',
                      transaction.distributedAt == null
                          ? 'Not yet distributed'
                          : readingTime(transaction.distributedAt!),
                    ),
                  ],
                ),
                const SizedBox(height: 12),
                const Card(
                  child: ListTile(
                    leading: Icon(Icons.lock_outline, color: emerald),
                    title: Text(
                      'Read-only record',
                      style: TextStyle(fontWeight: FontWeight.w700),
                    ),
                    subtitle: Text(
                      'Reward eligibility, quantity, unit, and distribution status are calculated and managed by the AQUASENSE+ backend and Barangay personnel.',
                    ),
                  ),
                ),
              ],
            ),
          ),
        ),
      ],
    ),
  );
}

class IncentiveStatusChip extends StatelessWidget {
  final String status;
  const IncentiveStatusChip({super.key, required this.status});

  @override
  Widget build(BuildContext context) {
    final code = status.trim().toUpperCase();
    final color = switch (code) {
      'DISTRIBUTED' => Colors.green,
      'CANCELLED' => Colors.red,
      'APPROVED_FOR_DISTRIBUTION' => Colors.blue,
      'CALCULATED' => Colors.orange,
      _ => Colors.blueGrey,
    };
    final icon = switch (code) {
      'DISTRIBUTED' => Icons.check_circle_outline,
      'CANCELLED' => Icons.cancel_outlined,
      'APPROVED_FOR_DISTRIBUTION' => Icons.inventory_2_outlined,
      'CALCULATED' => Icons.schedule_outlined,
      _ => Icons.info_outline,
    };
    return Chip(
      avatar: Icon(icon, size: 18, color: color.shade700),
      side: BorderSide(color: color.withValues(alpha: 0.45)),
      backgroundColor: color.withValues(alpha: 0.10),
      label: Text(
        incentiveStatusLabel(status),
        style: TextStyle(color: color.shade700, fontWeight: FontWeight.w700),
      ),
    );
  }
}

class _Section extends StatelessWidget {
  final String title;
  final List<Widget> children;
  const _Section({required this.title, required this.children});

  @override
  Widget build(BuildContext context) => Card(
    child: Padding(
      padding: const EdgeInsets.all(18),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(
            title,
            style: Theme.of(
              context,
            ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w800),
          ),
          const Divider(height: 24),
          ...children,
        ],
      ),
    ),
  );
}

class _Detail extends StatelessWidget {
  final String label;
  final String value;
  const _Detail(this.label, this.value);

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(bottom: 12),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        SizedBox(
          width: 140,
          child: Text(label, style: Theme.of(context).textTheme.bodySmall),
        ),
        Expanded(
          child: Text(
            value,
            style: const TextStyle(fontWeight: FontWeight.w600),
          ),
        ),
      ],
    ),
  );
}
