import 'dart:async';
import 'package:flutter/material.dart';
import '../../models/alert.dart';
import '../../services/alerts_service.dart';
import '../../services/api_service.dart';
import '../../services/auth_service.dart';
import '../../theme/app_theme.dart';
import '../../utils/formatters.dart';
import '../../widgets/loading_widget.dart';
import '../../widgets/state_panel.dart';
import 'alert_detail_screen.dart';

class AlertsScreen extends StatefulWidget {
  static const refreshInterval = Duration(seconds: 20);
  final AuthService auth;
  final AlertsRepository? repository;
  const AlertsScreen({super.key, required this.auth, this.repository});
  @override
  State<AlertsScreen> createState() => _AlertsScreenState();
}

class _AlertsScreenState extends State<AlertsScreen>
    with WidgetsBindingObserver {
  late final AlertsRepository repository;
  Timer? timer;
  AlertPage? data;
  AlertStatusFilter status = AlertStatusFilter.unresolved;
  AlertSeverityFilter severity = AlertSeverityFilter.all;
  AlertDateRange range = AlertDateRange.last30Days;
  DateTimeRange? custom;
  int? trapId;
  bool loading = true, refreshing = false, loadingMore = false;
  String? error, refreshError;
  @override
  void initState() {
    super.initState();
    repository = widget.repository ?? AlertsService(widget.auth);
    WidgetsBinding.instance.addObserver(this);
    _startTimer();
    _load();
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    timer?.cancel();
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) {
      _startTimer();
      _refresh();
    } else {
      timer?.cancel();
      timer = null;
    }
  }

  void _startTimer() {
    timer?.cancel();
    timer = Timer.periodic(AlertsScreen.refreshInterval, (_) => _refresh());
  }

  Future<AlertPage> _request([int page = 1]) => repository.loadAlerts(
    status: status,
    severity: severity,
    range: range,
    from: custom?.start,
    to: custom?.end,
    greaseTrapId: trapId,
    page: page,
  );
  Future<void> _load() async {
    setState(() {
      loading = true;
      error = null;
    });
    try {
      final value = await _request();
      if (mounted) setState(() => data = value);
    } on ApiException catch (e) {
      if (mounted && e.type != ApiErrorType.unauthorized) {
        setState(() => error = e.message);
      }
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  Future<void> _refresh() async {
    if (refreshing || loading) return;
    setState(() {
      refreshing = true;
      refreshError = null;
    });
    try {
      final value = await _request();
      if (mounted) setState(() => data = value);
    } on ApiException catch (e) {
      if (mounted && e.type != ApiErrorType.unauthorized) {
        setState(() => refreshError = e.message);
      }
    } finally {
      if (mounted) setState(() => refreshing = false);
    }
  }

  Future<void> _more() async {
    final old = data;
    if (old == null || !old.hasMore || loadingMore) return;
    setState(() => loadingMore = true);
    try {
      final next = await _request(old.page + 1);
      if (mounted) setState(() => data = old.append(next));
    } on ApiException catch (e) {
      if (mounted && e.type != ApiErrorType.unauthorized) {
        setState(() => refreshError = e.message);
      }
    } finally {
      if (mounted) setState(() => loadingMore = false);
    }
  }

  Future<void> _setRange(AlertDateRange next) async {
    if (next == AlertDateRange.custom) {
      final now = DateTime.now();
      final selected = await showDateRangePicker(
        context: context,
        firstDate: now.subtract(const Duration(days: 365)),
        lastDate: now,
        initialDateRange: custom,
        helpText: 'Select up to 31 days',
      );
      if (selected == null || !mounted) return;
      if (selected.duration.inDays > 30) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Custom range cannot exceed 31 days.')),
        );
        return;
      }
      custom = selected;
    }
    setState(() => range = next);
    await _load();
  }

  Future<void> _open(AlertItem item) async {
    await Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) =>
            AlertDetailScreen(alertId: item.id, repository: repository),
      ),
    );
    if (mounted) _refresh();
  }

  @override
  Widget build(BuildContext context) {
    if (loading && data == null) {
      return const LoadingWidget(label: 'Loading alerts…');
    }
    if (data == null) {
      return Padding(
        padding: const EdgeInsets.all(24),
        child: StatePanel(
          icon: Icons.notifications_off_outlined,
          title: 'Unable to load alerts',
          message: error ?? 'Alerts are temporarily unavailable.',
          actionLabel: 'Retry',
          onAction: _load,
        ),
      );
    }
    return RefreshIndicator(
      onRefresh: _refresh,
      child: ListView.builder(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 36),
        itemCount:
            1 +
            (data!.alerts.isEmpty ? 1 : data!.alerts.length) +
            (data!.alerts.isNotEmpty && data!.hasMore ? 1 : 0),
        itemBuilder: (context, index) {
          if (index == 0) {
            return Center(
              child: ConstrainedBox(
                constraints: const BoxConstraints(maxWidth: 920),
                child: _header(),
              ),
            );
          }
          if (index == 1 && data!.alerts.isEmpty) {
            return Center(
              child: ConstrainedBox(
                constraints: const BoxConstraints(maxWidth: 920),
                child: Padding(
                  padding: const EdgeInsets.only(top: 20),
                  child: StatePanel.empty(
                    icon: Icons.notifications_none,
                    title: status == AlertStatusFilter.unresolved
                        ? 'No active alerts.'
                        : 'No alert history is available yet.',
                    message: status == AlertStatusFilter.unresolved
                        ? 'No unresolved alert records were returned for this date range.'
                        : 'No alerts match the selected filters.',
                  ),
                ),
              ),
            );
          }
          if (data!.alerts.isNotEmpty &&
              data!.hasMore &&
              index == data!.alerts.length + 1) {
            return Center(
              child: Padding(
                padding: const EdgeInsets.only(top: 12),
                child: OutlinedButton.icon(
                  onPressed: loadingMore ? null : _more,
                  icon: loadingMore
                      ? const SizedBox(
                          width: 18,
                          height: 18,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        )
                      : const Icon(Icons.expand_more),
                  label: const Text('Load more'),
                ),
              ),
            );
          }
          final alert = data!.alerts[index - 1];
          return Center(
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 920),
              child: Padding(
                padding: const EdgeInsets.only(top: 12),
                child: _card(alert),
              ),
            ),
          );
        },
      ),
    );
  }

  Widget _header() => Column(
    crossAxisAlignment: CrossAxisAlignment.stretch,
    children: [
      if (refreshing) const LinearProgressIndicator(),
      if (refreshError != null)
        Container(
          margin: const EdgeInsets.only(bottom: 12),
          padding: const EdgeInsets.all(12),
          decoration: BoxDecoration(
            color: Colors.orange.shade50,
            borderRadius: BorderRadius.circular(12),
          ),
          child: const Text(
            'Unable to refresh alerts. Showing the latest available data.',
          ),
        ),
      Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text(
                  'Owner Alerts',
                  style: TextStyle(
                    color: forest,
                    fontSize: 24,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                Text(
                  'Read-only alerts · updated ${readingTime(DateTime.now().toUtc())}',
                ),
              ],
            ),
          ),
          IconButton(
            tooltip: 'Refresh alerts',
            onPressed: refreshing ? null : _refresh,
            icon: const Icon(Icons.refresh),
          ),
        ],
      ),
      const SizedBox(height: 14),
      _summary(),
      const SizedBox(height: 14),
      Wrap(
        spacing: 12,
        runSpacing: 12,
        children: [
          SizedBox(
            width: 190,
            child: DropdownButtonFormField<AlertStatusFilter>(
              isExpanded: true,
              initialValue: status,
              decoration: const InputDecoration(labelText: 'Status'),
              items: [
                for (final v in AlertStatusFilter.values)
                  DropdownMenuItem(value: v, child: Text(v.label)),
              ],
              onChanged: (v) {
                if (v != null) {
                  setState(() => status = v);
                  _load();
                }
              },
            ),
          ),
          SizedBox(
            width: 190,
            child: DropdownButtonFormField<AlertSeverityFilter>(
              isExpanded: true,
              initialValue: severity,
              decoration: const InputDecoration(labelText: 'Severity'),
              items: [
                for (final v in AlertSeverityFilter.values)
                  DropdownMenuItem(value: v, child: Text(v.label)),
              ],
              onChanged: (v) {
                if (v != null) {
                  setState(() => severity = v);
                  _load();
                }
              },
            ),
          ),
          SizedBox(
            width: 190,
            child: DropdownButtonFormField<AlertDateRange>(
              isExpanded: true,
              initialValue: range,
              decoration: const InputDecoration(labelText: 'Date Range'),
              items: [
                for (final v in AlertDateRange.values)
                  DropdownMenuItem(value: v, child: Text(v.label)),
              ],
              onChanged: (v) {
                if (v != null) _setRange(v);
              },
            ),
          ),
          SizedBox(
            width: 250,
            child: DropdownButtonFormField<int?>(
              isExpanded: true,
              initialValue: trapId,
              decoration: const InputDecoration(labelText: 'Grease Trap'),
              items: [
                const DropdownMenuItem(
                  value: null,
                  child: Text('All authorized traps'),
                ),
                for (final t in data!.traps)
                  DropdownMenuItem(
                    value: t.id,
                    child: Text(
                      '${t.name} · ${t.businessName}',
                      overflow: TextOverflow.ellipsis,
                    ),
                  ),
              ],
              onChanged: (v) {
                setState(() => trapId = v);
                _load();
              },
            ),
          ),
        ],
      ),
    ],
  );
  Widget _summary() {
    final s = data!.summary;
    return Wrap(
      spacing: 10,
      runSpacing: 10,
      children: [
        _count(
          'Unresolved',
          s.unresolvedCount,
          Icons.notifications_active,
          Colors.red.shade700,
        ),
        _count(
          'Critical',
          s.criticalCount,
          Icons.error_outline,
          Colors.red.shade700,
        ),
        _count(
          'Warning',
          s.warningCount,
          Icons.warning_amber_outlined,
          Colors.orange.shade800,
        ),
        _count(
          'Resolved',
          s.resolvedCount,
          Icons.check_circle_outline,
          Colors.green.shade700,
        ),
      ],
    );
  }

  Widget _count(String label, int count, IconData icon, Color color) =>
      Container(
        constraints: const BoxConstraints(minWidth: 135),
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          color: color.withValues(alpha: .08),
          border: Border.all(color: color.withValues(alpha: .25)),
          borderRadius: BorderRadius.circular(14),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(icon, color: color),
            const SizedBox(width: 10),
            Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  '$count',
                  style: const TextStyle(
                    fontSize: 22,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                Text(label),
              ],
            ),
          ],
        ),
      );
  Widget _card(AlertItem a) {
    final color = _color(a.severity);
    return Semantics(
      button: true,
      label: '${a.label}, ${a.severity}, ${a.status}',
      child: Card(
        child: InkWell(
          borderRadius: BorderRadius.circular(16),
          onTap: () => _open(a),
          child: Padding(
            padding: const EdgeInsets.all(18),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Icon(_icon(a.severity), color: color),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            a.label,
                            style: const TextStyle(
                              fontSize: 18,
                              fontWeight: FontWeight.w800,
                            ),
                          ),
                          Text(
                            '${a.greaseTrapName} · ${a.businessName}',
                            overflow: TextOverflow.ellipsis,
                          ),
                        ],
                      ),
                    ),
                    const Icon(Icons.chevron_right),
                  ],
                ),
                const SizedBox(height: 10),
                Text(a.message, maxLines: 3, overflow: TextOverflow.ellipsis),
                const SizedBox(height: 12),
                Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: [
                    _chip(a.severity, color),
                    _chip(a.status, _statusColor(a.status)),
                    if (a.deviceCode != null) Chip(label: Text(a.deviceCode!)),
                  ],
                ),
                const SizedBox(height: 8),
                Text(
                  'Last triggered ${relativeTime(a.lastTriggeredAt)} · ${readingTime(a.lastTriggeredAt)}',
                  style: Theme.of(context).textTheme.bodySmall,
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
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
