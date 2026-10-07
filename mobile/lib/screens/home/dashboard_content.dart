import 'package:flutter/material.dart';

import '../../models/alert_summary.dart';
import '../../models/dashboard_snapshot.dart';
import '../../models/establishment.dart';
import '../../models/incentive_summary.dart';
import '../../theme/app_theme.dart';
import '../../utils/formatters.dart';
import '../../widgets/state_panel.dart';
import '../../widgets/status_card.dart';

class DashboardContent extends StatelessWidget {
  final DashboardSnapshot snapshot;
  final bool refreshing;
  final String? refreshError;
  final Future<void> Function() onRefresh;
  final ValueChanged<int> onNavigate;
  final ValueChanged<int>? onOpenAlert;

  const DashboardContent({
    super.key,
    required this.snapshot,
    required this.refreshing,
    required this.refreshError,
    required this.onRefresh,
    required this.onNavigate,
    this.onOpenAlert,
  });

  @override
  Widget build(BuildContext context) {
    final dashboard = snapshot.dashboard;
    return RefreshIndicator(
      onRefresh: onRefresh,
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(20, 12, 20, 32),
        children: [
          Center(
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 920),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  if (refreshing) const LinearProgressIndicator(),
                  if (refreshError != null) ...[
                    _RefreshNotice(message: refreshError!),
                    const SizedBox(height: 12),
                  ],
                  Text(
                    'Welcome, ${dashboard.user.fullName}',
                    style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                      color: forest,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    dashboard.establishments.isEmpty
                        ? 'No establishment is linked to your account.'
                        : dashboard.establishments
                              .map((site) => site.businessName)
                              .join(' · '),
                    style: Theme.of(context).textTheme.titleMedium,
                  ),
                  const SizedBox(height: 6),
                  Text(
                    'Dashboard updated ${readingTime(dashboard.generatedAt)}',
                    style: Theme.of(context).textTheme.bodySmall,
                  ),
                  const SizedBox(height: 20),
                  if (_hasCriticalCondition(dashboard)) ...[
                    const _CriticalBanner(),
                    const SizedBox(height: 20),
                  ],
                  if (dashboard.establishments.isEmpty)
                    const StatePanel.empty(
                      icon: Icons.storefront_outlined,
                      title: 'No registered establishment',
                      message:
                          'Contact your Barangay administrator to link an establishment to this owner account.',
                    )
                  else
                    for (final establishment in dashboard.establishments) ...[
                      _EstablishmentHeader(establishment: establishment),
                      const SizedBox(height: 12),
                      if (establishment.traps.isEmpty)
                        const StatePanel.empty(
                          icon: Icons.water_outlined,
                          title: 'No grease trap registered',
                          message:
                              'No grease trap is linked to this establishment yet.',
                        )
                      else
                        for (final trap in establishment.traps) ...[
                          StatusCard(trap: trap),
                          const SizedBox(height: 24),
                        ],
                    ],
                  _AlertSummaryCard(
                    alerts: dashboard.alerts,
                    onOpen: () => onNavigate(2),
                    onOpenAlert: onOpenAlert,
                  ),
                  const SizedBox(height: 16),
                  _OilSurrenderCard(
                    snapshot: snapshot,
                    onOpen: () => onNavigate(3),
                  ),
                  const SizedBox(height: 16),
                  _IncentiveCard(
                    snapshot: snapshot,
                    onOpen: () => onNavigate(4),
                  ),
                  const SizedBox(height: 24),
                  _QuickActions(onNavigate: onNavigate),
                  const SizedBox(height: 16),
                  OutlinedButton.icon(
                    onPressed: refreshing ? null : onRefresh,
                    icon: const Icon(Icons.refresh),
                    label: const Text('Refresh dashboard'),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  bool _hasCriticalCondition(OwnerDashboard dashboard) {
    if (dashboard.alerts.any((alert) => alert.severity == 'CRITICAL')) {
      return true;
    }
    return dashboard.establishments
        .expand((site) => site.traps)
        .any((trap) => const {'CRITICAL', 'OVERFLOW'}.contains(trap.status));
  }
}

class _RefreshNotice extends StatelessWidget {
  final String message;

  const _RefreshNotice({required this.message});

  @override
  Widget build(BuildContext context) => Material(
    color: const Color(0xFFFFF3CD),
    borderRadius: BorderRadius.circular(14),
    child: Padding(
      padding: const EdgeInsets.all(14),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Icon(Icons.info_outline, color: Color(0xFF805600)),
          const SizedBox(width: 10),
          Expanded(
            child: Text(
              'Unable to refresh. Showing the last loaded data. $message',
            ),
          ),
        ],
      ),
    ),
  );
}

class _CriticalBanner extends StatelessWidget {
  const _CriticalBanner();

  @override
  Widget build(BuildContext context) => Semantics(
    liveRegion: true,
    child: Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: const Color(0xFF9B2C22),
        borderRadius: BorderRadius.circular(18),
      ),
      child: const Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(Icons.warning_amber_rounded, color: Colors.white, size: 30),
          SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Critical attention required',
                  style: TextStyle(
                    color: Colors.white,
                    fontSize: 18,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                SizedBox(height: 4),
                Text(
                  'Review the condition and active alerts below.',
                  style: TextStyle(color: Colors.white),
                ),
              ],
            ),
          ),
        ],
      ),
    ),
  );
}

class _EstablishmentHeader extends StatelessWidget {
  final Establishment establishment;

  const _EstablishmentHeader({required this.establishment});

  @override
  Widget build(BuildContext context) => Card(
    child: ListTile(
      leading: const Icon(Icons.storefront_outlined, color: emerald),
      title: Text(
        establishment.businessName,
        style: const TextStyle(fontWeight: FontWeight.w700),
      ),
      subtitle: Text(
        '${establishment.traps.length} grease trap'
        '${establishment.traps.length == 1 ? '' : 's'}',
      ),
    ),
  );
}

class _AlertSummaryCard extends StatelessWidget {
  final List<OwnerAlert> alerts;
  final VoidCallback onOpen;
  final ValueChanged<int>? onOpenAlert;

  const _AlertSummaryCard({
    required this.alerts,
    required this.onOpen,
    this.onOpenAlert,
  });

  @override
  Widget build(BuildContext context) {
    OwnerAlert? highest;
    for (final alert in alerts) {
      if (highest == null ||
          _severityRank(alert.severity) > _severityRank(highest.severity)) {
        highest = alert;
      }
    }
    final latest = alerts.isEmpty ? null : alerts.first;
    return _SummaryCard(
      icon: Icons.notifications_outlined,
      title: 'Alerts',
      actionLabel: 'View Alerts',
      onAction: onOpen,
      child: alerts.isEmpty
          ? const Text('No active alerts')
          : Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  '${alerts.length} active alert${alerts.length == 1 ? '' : 's'}',
                  style: Theme.of(context).textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.w700,
                  ),
                ),
                const SizedBox(height: 8),
                _StatusLabel(highest!.severity),
                const SizedBox(height: 8),
                InkWell(
                  onTap: onOpenAlert == null
                      ? onOpen
                      : () => onOpenAlert!(latest!.id),
                  child: Padding(
                    padding: const EdgeInsets.symmetric(vertical: 4),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          latest!.label,
                          style: const TextStyle(fontWeight: FontWeight.w700),
                        ),
                        Text(latest.message),
                      ],
                    ),
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  '${latest.greaseTrapName} · ${readingTime(latest.lastTriggeredAt)}',
                  style: Theme.of(context).textTheme.bodySmall,
                ),
              ],
            ),
    );
  }

  int _severityRank(String severity) => switch (severity) {
    'CRITICAL' => 3,
    'WARNING' => 2,
    _ => 1,
  };
}

class _OilSurrenderCard extends StatelessWidget {
  final DashboardSnapshot snapshot;
  final VoidCallback onOpen;

  const _OilSurrenderCard({required this.snapshot, required this.onOpen});

  @override
  Widget build(BuildContext context) {
    final summary = snapshot.oilSurrenders;
    final latest = summary.latest;
    return _SummaryCard(
      icon: Icons.oil_barrel_outlined,
      title: 'Oil Surrender',
      actionLabel: 'View Oil Surrenders',
      onAction: onOpen,
      child: latest == null
          ? const Text('No oil surrender records yet.')
          : Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Pending submissions: ${summary.pendingCount}',
                  style: const TextStyle(fontWeight: FontWeight.w700),
                ),
                const SizedBox(height: 8),
                Text('Latest: ${latest.transactionCode}'),
                Text(
                  '${cleanNumber(latest.quantity)} ${latest.unit} · ${latest.status}',
                ),
                Text(
                  readingTime(latest.submittedAt),
                  style: Theme.of(context).textTheme.bodySmall,
                ),
              ],
            ),
    );
  }
}

class _IncentiveCard extends StatelessWidget {
  final DashboardSnapshot snapshot;
  final VoidCallback onOpen;

  const _IncentiveCard({required this.snapshot, required this.onOpen});

  @override
  Widget build(BuildContext context) {
    final summary = snapshot.incentives;
    return _SummaryCard(
      icon: Icons.card_giftcard_outlined,
      title: 'Rice Incentives',
      actionLabel: 'View Incentives',
      onAction: onOpen,
      child: summary.units.isEmpty
          ? const Text('No incentive records yet.')
          : Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                for (final unit in summary.units)
                  Padding(
                    padding: const EdgeInsets.only(bottom: 8),
                    child: Text(
                      '${cleanQuantity(unit.pending)} ${unit.unit} pending · '
                      '${cleanQuantity(unit.distributed)} ${unit.unit} distributed · '
                      '${cleanQuantity(unit.earned)} ${unit.unit} earned',
                    ),
                  ),
                if (summary.latest != null)
                  Text(
                    'Latest status: ${incentiveStatusLabel(summary.latest!.status)}',
                    style: const TextStyle(fontWeight: FontWeight.w700),
                  ),
              ],
            ),
    );
  }
}

class _QuickActions extends StatelessWidget {
  final ValueChanged<int> onNavigate;

  const _QuickActions({required this.onNavigate});

  @override
  Widget build(BuildContext context) => Column(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      Text(
        'Quick actions',
        style: Theme.of(
          context,
        ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w700),
      ),
      const SizedBox(height: 12),
      Wrap(
        spacing: 10,
        runSpacing: 10,
        children: [
          _action(1, Icons.monitor_heart_outlined, 'Monitoring'),
          _action(2, Icons.notifications_outlined, 'Alerts'),
          _action(3, Icons.oil_barrel_outlined, 'Oil Surrender'),
          _action(4, Icons.card_giftcard_outlined, 'Incentives'),
          _action(5, Icons.person_outline, 'Profile'),
        ],
      ),
    ],
  );

  Widget _action(int index, IconData icon, String label) => OutlinedButton.icon(
    onPressed: () => onNavigate(index),
    icon: Icon(icon),
    label: Text(label),
  );
}

class _SummaryCard extends StatelessWidget {
  final IconData icon;
  final String title;
  final Widget child;
  final String actionLabel;
  final VoidCallback onAction;

  const _SummaryCard({
    required this.icon,
    required this.title,
    required this.child,
    required this.actionLabel,
    required this.onAction,
  });

  @override
  Widget build(BuildContext context) => Card(
    child: Padding(
      padding: const EdgeInsets.all(20),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              Icon(icon, color: emerald),
              const SizedBox(width: 10),
              Expanded(
                child: Text(
                  title,
                  style: Theme.of(
                    context,
                  ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w700),
                ),
              ),
            ],
          ),
          const SizedBox(height: 14),
          child,
          const SizedBox(height: 12),
          Align(
            alignment: Alignment.centerRight,
            child: TextButton(onPressed: onAction, child: Text(actionLabel)),
          ),
        ],
      ),
    ),
  );
}

class _StatusLabel extends StatelessWidget {
  final String label;

  const _StatusLabel(this.label);

  @override
  Widget build(BuildContext context) => Align(
    alignment: Alignment.centerLeft,
    child: Chip(
      avatar: const Icon(Icons.warning_amber_rounded, size: 18),
      label: Text(label),
    ),
  );
}
