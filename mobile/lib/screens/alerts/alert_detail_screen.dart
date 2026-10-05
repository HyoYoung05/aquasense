import 'package:flutter/material.dart';
import '../../models/alert.dart';
import '../../services/alerts_service.dart';
import '../../services/api_service.dart';
import '../../theme/app_theme.dart';
import '../../utils/formatters.dart';
import '../../widgets/loading_widget.dart';
import '../../widgets/state_panel.dart';

class AlertDetailScreen extends StatefulWidget {
  final int alertId;
  final AlertsRepository repository;
  const AlertDetailScreen({
    super.key,
    required this.alertId,
    required this.repository,
  });
  @override
  State<AlertDetailScreen> createState() => _AlertDetailScreenState();
}

class _AlertDetailScreenState extends State<AlertDetailScreen> {
  AlertItem? alert;
  String? error;
  bool loading = true;
  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      loading = true;
      error = null;
    });
    try {
      final value = await widget.repository.loadAlert(widget.alertId);
      if (mounted) setState(() => alert = value);
    } on ApiException catch (e) {
      if (mounted && e.type != ApiErrorType.unauthorized) {
        setState(() => error = e.message);
      }
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Alert Details')),
    body: SafeArea(
      child: loading
          ? const LoadingWidget(label: 'Loading alert…')
          : alert == null
          ? Padding(
              padding: const EdgeInsets.all(24),
              child: StatePanel(
                icon: Icons.warning_amber_outlined,
                title: 'Unable to load alert',
                message: error ?? 'The alert is unavailable.',
                actionLabel: 'Retry',
                onAction: _load,
              ),
            )
          : RefreshIndicator(
              onRefresh: _load,
              child: ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                padding: const EdgeInsets.all(20),
                children: [
                  Center(
                    child: ConstrainedBox(
                      constraints: const BoxConstraints(maxWidth: 760),
                      child: _content(context, alert!),
                    ),
                  ),
                ],
              ),
            ),
    ),
  );
  Widget _content(BuildContext c, AlertItem a) => Card(
    child: Padding(
      padding: const EdgeInsets.all(22),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Icon(_icon(a.severity), color: _color(a.severity), size: 34),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      a.label,
                      style: Theme.of(c).textTheme.headlineSmall?.copyWith(
                        fontWeight: FontWeight.w800,
                        color: forest,
                      ),
                    ),
                    const SizedBox(height: 6),
                    Wrap(
                      spacing: 8,
                      runSpacing: 8,
                      children: [
                        _chip(a.severity, _color(a.severity)),
                        _chip(a.status, _statusColor(a.status)),
                      ],
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 18),
          Text(a.message),
          const Divider(height: 32),
          _row('Establishment', a.businessName),
          _row('Grease Trap', a.greaseTrapName),
          _row('Device', a.deviceCode ?? 'Not available'),
          _row('Sensor', a.sensorName ?? 'Not available'),
          _row(
            'Trigger Value',
            a.sensorValue == null
                ? 'Not available'
                : cleanNumber(a.sensorValue!),
          ),
          _row(
            'Threshold Value',
            a.thresholdValue == null
                ? 'Not available'
                : cleanNumber(a.thresholdValue!),
          ),
          _row('First Triggered', readingTime(a.firstTriggeredAt)),
          _row(
            'Last Triggered',
            '${relativeTime(a.lastTriggeredAt)} · ${readingTime(a.lastTriggeredAt)}',
          ),
          _row(
            'Acknowledged At',
            a.acknowledgedAt == null
                ? 'Not acknowledged'
                : readingTime(a.acknowledgedAt!),
          ),
          _row(
            'Resolved At',
            a.resolvedAt == null ? 'Not resolved' : readingTime(a.resolvedAt!),
          ),
          _row('Trigger Count', '${a.triggerCount}'),
          const SizedBox(height: 12),
          const Text(
            'Alert actions are managed by authorized Barangay personnel.',
            style: TextStyle(fontStyle: FontStyle.italic),
          ),
        ],
      ),
    ),
  );
  Widget _row(String label, String value) => Padding(
    padding: const EdgeInsets.only(bottom: 12),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        SizedBox(
          width: 145,
          child: Text(
            label,
            style: const TextStyle(fontWeight: FontWeight.w700),
          ),
        ),
        Expanded(child: Text(value)),
      ],
    ),
  );
}

Widget _chip(String text, Color color) => Chip(
  avatar: Icon(Icons.circle, size: 10, color: color),
  label: Text(text),
);
Color _color(String s) => switch (s) {
  'CRITICAL' => Colors.red.shade700,
  'WARNING' => Colors.orange.shade800,
  'INFO' => Colors.blue.shade700,
  _ => Colors.blueGrey,
};
Color _statusColor(String s) => switch (s) {
  'ACTIVE' => Colors.red.shade700,
  'ACKNOWLEDGED' => Colors.orange.shade800,
  'RESOLVED' => Colors.green.shade700,
  _ => Colors.blueGrey,
};
IconData _icon(String s) => s == 'CRITICAL'
    ? Icons.error_outline
    : s == 'WARNING'
    ? Icons.warning_amber_outlined
    : Icons.info_outline;
