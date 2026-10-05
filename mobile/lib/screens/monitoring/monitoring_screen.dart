import 'dart:async';

import 'package:fl_chart/fl_chart.dart';
import 'package:flutter/material.dart';

import '../../models/monitoring.dart';
import '../../services/api_service.dart';
import '../../services/auth_service.dart';
import '../../services/monitoring_service.dart';
import '../../services/alerts_service.dart';
import '../../theme/app_theme.dart';
import '../../utils/formatters.dart';
import '../../widgets/loading_widget.dart';
import '../../widgets/state_panel.dart';
import '../alerts/alert_detail_screen.dart';

class MonitoringScreen extends StatefulWidget {
  static const refreshInterval = Duration(seconds: 20);

  final AuthService auth;
  final MonitoringRepository? repository;
  final AlertsRepository? alertsRepository;

  const MonitoringScreen({
    super.key,
    required this.auth,
    this.repository,
    this.alertsRepository,
  });

  @override
  State<MonitoringScreen> createState() => _MonitoringScreenState();
}

class _MonitoringScreenState extends State<MonitoringScreen>
    with WidgetsBindingObserver {
  late final MonitoringRepository repository;
  Timer? refreshTimer;
  MonitoringSnapshot? snapshot;
  TelemetryHistory? history;
  int? selectedTrapId;
  HistoryRange range = HistoryRange.today;
  SensorMetric selectedMetric = SensorMetric.wasteLevel;
  DateTimeRange? customRange;
  String? initialError;
  String? refreshError;
  String? historyError;
  bool loading = true;
  bool refreshing = false;
  bool historyLoading = false;
  bool loadingMore = false;

  @override
  void initState() {
    super.initState();
    repository = widget.repository ?? MonitoringService(widget.auth);
    WidgetsBinding.instance.addObserver(this);
    _startTimer();
    _loadInitial();
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    refreshTimer?.cancel();
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) {
      _startTimer();
      _refreshCurrent();
    } else {
      refreshTimer?.cancel();
      refreshTimer = null;
    }
  }

  void _startTimer() {
    refreshTimer?.cancel();
    refreshTimer = Timer.periodic(
      MonitoringScreen.refreshInterval,
      (_) => _refreshCurrent(),
    );
  }

  Future<void> _loadInitial() async {
    try {
      final current = await repository.loadCurrent();
      if (!mounted) return;
      setState(() {
        snapshot = current;
        selectedTrapId = current.traps.isEmpty
            ? null
            : (current.traps.any((trap) => trap.greaseTrapId == selectedTrapId)
                  ? selectedTrapId
                  : current.traps.first.greaseTrapId);
        initialError = null;
      });
      if (selectedTrapId != null) await _loadHistory();
    } on ApiException catch (error) {
      if (mounted && error.type != ApiErrorType.unauthorized) {
        setState(() => initialError = error.message);
      }
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  Future<void> _refreshCurrent() async {
    if (refreshing || loading) return;
    setState(() {
      refreshing = true;
      refreshError = null;
    });
    try {
      final current = await repository.loadCurrent();
      if (!mounted) return;
      setState(() {
        snapshot = current;
        if (!current.traps.any((trap) => trap.greaseTrapId == selectedTrapId)) {
          selectedTrapId = current.traps.isEmpty
              ? null
              : current.traps.first.greaseTrapId;
        }
      });
    } on ApiException catch (error) {
      if (mounted && error.type != ApiErrorType.unauthorized) {
        setState(() => refreshError = error.message);
      }
    } finally {
      if (mounted) setState(() => refreshing = false);
    }
  }

  Future<void> _manualRefresh() async {
    await _refreshCurrent();
    if (selectedTrapId != null) await _loadHistory(preserve: true);
  }

  Future<void> _loadHistory({bool preserve = false}) async {
    final trapId = selectedTrapId;
    if (trapId == null || historyLoading) return;
    setState(() {
      historyLoading = true;
      historyError = null;
      if (!preserve) history = null;
    });
    try {
      final result = await repository.loadHistory(
        greaseTrapId: trapId,
        range: range,
        from: customRange?.start,
        to: customRange?.end,
      );
      if (!mounted || trapId != selectedTrapId) return;
      setState(() {
        history = result;
        selectedMetric = _availableMetrics(result).contains(selectedMetric)
            ? selectedMetric
            : (_availableMetrics(result).firstOrNull ??
                  SensorMetric.wasteLevel);
      });
    } on ApiException catch (error) {
      if (mounted && error.type != ApiErrorType.unauthorized) {
        setState(() => historyError = error.message);
      }
    } finally {
      if (mounted) setState(() => historyLoading = false);
    }
  }

  Future<void> _loadMore() async {
    final current = history;
    if (current == null || !current.hasMore || loadingMore) return;
    setState(() => loadingMore = true);
    try {
      final next = await repository.loadHistory(
        greaseTrapId: current.trapId,
        range: range,
        from: customRange?.start,
        to: customRange?.end,
        page: current.page + 1,
      );
      if (mounted) setState(() => history = current.append(next));
    } on ApiException catch (error) {
      if (mounted && error.type != ApiErrorType.unauthorized) {
        setState(() => historyError = error.message);
      }
    } finally {
      if (mounted) setState(() => loadingMore = false);
    }
  }

  Future<void> _selectRange(HistoryRange value) async {
    if (value == HistoryRange.custom) {
      final now = DateTime.now();
      final chosen = await showDateRangePicker(
        context: context,
        firstDate: now.subtract(const Duration(days: 365)),
        lastDate: now,
        initialDateRange: customRange,
        helpText: 'Select up to 31 days',
      );
      if (chosen == null || !mounted) return;
      if (chosen.duration.inDays > 30) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Custom range cannot exceed 31 days.')),
        );
        return;
      }
      customRange = chosen;
    }
    setState(() => range = value);
    await _loadHistory();
  }

  Future<void> _selectTrap(int? id) async {
    if (id == null || id == selectedTrapId) return;
    setState(() {
      selectedTrapId = id;
      history = null;
      historyError = null;
    });
    await _loadHistory();
  }

  GreaseTrapMonitoring? get selectedTrap {
    for (final trap in snapshot?.traps ?? const <GreaseTrapMonitoring>[]) {
      if (trap.greaseTrapId == selectedTrapId) return trap;
    }
    return null;
  }

  @override
  Widget build(BuildContext context) {
    if (loading && snapshot == null) {
      return const LoadingWidget(label: 'Loading monitoring…');
    }
    if (snapshot == null) {
      return Padding(
        padding: const EdgeInsets.all(24),
        child: StatePanel(
          icon: Icons.cloud_off_outlined,
          title: 'Unable to load monitoring',
          message: initialError ?? 'Monitoring is temporarily unavailable.',
          actionLabel: 'Retry',
          onAction: () {
            setState(() => loading = true);
            _loadInitial();
          },
        ),
      );
    }
    return RefreshIndicator(
      onRefresh: _manualRefresh,
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 36),
        children: [
          Center(
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 980),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  if (refreshing) const LinearProgressIndicator(),
                  if (refreshError != null) ...[
                    _Notice(
                      'Unable to refresh. Showing the latest available reading. $refreshError',
                    ),
                    const SizedBox(height: 12),
                  ],
                  _header(),
                  const SizedBox(height: 16),
                  if (snapshot!.traps.isEmpty)
                    const StatePanel.empty(
                      icon: Icons.sensors_off_outlined,
                      title: 'No grease trap available',
                      message:
                          'No active grease trap is linked to this owner account.',
                    )
                  else ...[
                    _trapSelector(),
                    const SizedBox(height: 16),
                    _currentStatus(selectedTrap!),
                    const SizedBox(height: 16),
                    _sensorOverview(selectedTrap!),
                    const SizedBox(height: 24),
                    _historySection(),
                  ],
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _header() => Row(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      const Expanded(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'Grease Trap Monitoring',
              style: TextStyle(
                color: forest,
                fontSize: 24,
                fontWeight: FontWeight.w800,
              ),
            ),
            SizedBox(height: 4),
            Text('Current sensor readings and bounded telemetry history'),
          ],
        ),
      ),
      IconButton(
        tooltip: 'Refresh monitoring',
        onPressed: refreshing ? null : _manualRefresh,
        icon: const Icon(Icons.refresh),
      ),
    ],
  );

  Widget _trapSelector() => DropdownButtonFormField<int>(
    key: const Key('grease-trap-selector'),
    isExpanded: true,
    initialValue: selectedTrapId,
    decoration: const InputDecoration(
      labelText: 'Grease Trap',
      prefixIcon: Icon(Icons.water_outlined),
    ),
    items: [
      for (final trap in snapshot!.traps)
        DropdownMenuItem(
          value: trap.greaseTrapId,
          child: Text('${trap.greaseTrapName} · ${trap.businessName}'),
        ),
    ],
    onChanged: _selectTrap,
  );

  Widget _currentStatus(GreaseTrapMonitoring trap) {
    final reading = trap.reading;
    final offline = trap.deviceStatus != 'ONLINE';
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(20),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Icon(
                  offline ? Icons.cloud_off_outlined : Icons.sensors,
                  color: offline ? Colors.deepOrange : emerald,
                  size: 30,
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        trap.greaseTrapName,
                        style: Theme.of(context).textTheme.titleLarge?.copyWith(
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                      Text(trap.businessName),
                    ],
                  ),
                ),
                _StatusChip(trap.sensorState),
              ],
            ),
            const Divider(height: 30),
            Wrap(
              spacing: 24,
              runSpacing: 14,
              children: [
                _fact(
                  'Waste Level',
                  reading?.wasteLevel == null
                      ? 'No data'
                      : '${cleanNumber(reading!.wasteLevel!)}%',
                ),
                _fact('Current Condition', trap.sensorState),
                _fact(
                  'Distance to Waste Surface',
                  reading?.ultrasonicDistance == null
                      ? 'Not available'
                      : '${cleanNumber(reading!.ultrasonicDistance!)} cm',
                ),
                _fact('Assigned Device', trap.deviceCode ?? 'Not assigned'),
                _fact('Device Status', trap.deviceStatus),
                _fact(
                  'Last Seen',
                  trap.lastSeenAt == null
                      ? 'Not available'
                      : '${relativeTime(trap.lastSeenAt!)} · ${readingTime(trap.lastSeenAt!)}',
                ),
                _fact(
                  'Last Telemetry',
                  reading == null ? 'No data' : readingTime(reading.recordedAt),
                ),
              ],
            ),
            if (reading == null) ...[
              const SizedBox(height: 18),
              const _InlineState(
                icon: Icons.hourglass_empty,
                text: 'Awaiting Sensor Data',
              ),
            ] else if (trap.isStale || offline) ...[
              const SizedBox(height: 18),
              _InlineState(
                icon: Icons.history,
                text:
                    'Device Offline · values shown are the last known readings from ${readingTime(reading.recordedAt)}.',
              ),
            ],
            if (reading?.isTest == true || reading?.isSimulated == true) ...[
              const SizedBox(height: 12),
              Text(
                [
                  if (reading!.isTest) 'TEST',
                  if (reading.isSimulated) 'SIMULATED',
                ].join(' · '),
                style: Theme.of(context).textTheme.labelLarge,
              ),
            ],
            if (trap.activeAlert case final alert?) ...[
              const SizedBox(height: 16),
              Container(
                padding: const EdgeInsets.all(14),
                decoration: BoxDecoration(
                  color:
                      (alert.severity == 'CRITICAL'
                              ? Colors.red
                              : Colors.orange)
                          .withValues(alpha: .08),
                  border: Border.all(
                    color: alert.severity == 'CRITICAL'
                        ? Colors.red.shade300
                        : Colors.orange.shade300,
                  ),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Row(
                  children: [
                    Icon(
                      alert.severity == 'CRITICAL'
                          ? Icons.error_outline
                          : Icons.warning_amber_outlined,
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            '${alert.label} · ${alert.status}',
                            style: const TextStyle(fontWeight: FontWeight.w800),
                          ),
                          Text(
                            alert.message,
                            maxLines: 2,
                            overflow: TextOverflow.ellipsis,
                          ),
                        ],
                      ),
                    ),
                    TextButton(
                      key: Key('monitoring-alert-${alert.id}'),
                      onPressed: () => Navigator.of(context).push(
                        MaterialPageRoute(
                          builder: (_) => AlertDetailScreen(
                            alertId: alert.id,
                            repository:
                                widget.alertsRepository ??
                                AlertsService(widget.auth),
                          ),
                        ),
                      ),
                      child: const Text('View Alert'),
                    ),
                  ],
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }

  Widget _fact(String label, String value) => SizedBox(
    width: 250,
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(label, style: Theme.of(context).textTheme.bodySmall),
        const SizedBox(height: 3),
        Text(value, style: const TextStyle(fontWeight: FontWeight.w700)),
      ],
    ),
  );

  Widget _sensorOverview(GreaseTrapMonitoring trap) {
    final reading = trap.reading;
    final width = MediaQuery.sizeOf(context).width;
    final columns = width >= 720 ? 4 : (width < 420 ? 1 : 2);
    final sensors = [
      ('Temperature', reading?.temperature, '°C', Icons.thermostat_outlined),
      ('Turbidity', reading?.turbidity, 'NTU', Icons.opacity_outlined),
      ('Flow Rate', reading?.flowRate, 'L/min', Icons.air_outlined),
      ('Gas Sensor Reading', reading?.gasValue, 'raw', Icons.co2_outlined),
    ];
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          'Current Sensors',
          style: Theme.of(
            context,
          ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w800),
        ),
        const SizedBox(height: 12),
        GridView.builder(
          shrinkWrap: true,
          physics: const NeverScrollableScrollPhysics(),
          gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
            crossAxisCount: columns,
            childAspectRatio: columns == 1 ? 2.0 : 1.35,
            crossAxisSpacing: 10,
            mainAxisSpacing: 10,
          ),
          itemCount: sensors.length,
          itemBuilder: (context, index) {
            final sensor = sensors[index];
            return Card(
              child: Padding(
                padding: const EdgeInsets.all(14),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Icon(sensor.$4, color: emerald),
                    const SizedBox(height: 8),
                    Text(sensor.$1),
                    const SizedBox(height: 3),
                    Text(
                      sensor.$2 == null
                          ? 'Not connected'
                          : '${cleanNumber(sensor.$2!)} ${sensor.$3}',
                      style: const TextStyle(fontWeight: FontWeight.w800),
                    ),
                  ],
                ),
              ),
            );
          },
        ),
      ],
    );
  }

  Widget _historySection() => Card(
    child: Padding(
      padding: const EdgeInsets.all(20),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(
            'Telemetry History',
            style: Theme.of(
              context,
            ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w800),
          ),
          const SizedBox(height: 12),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              for (final option in HistoryRange.values)
                ChoiceChip(
                  key: Key('range-${option.apiValue}'),
                  label: Text(option.label),
                  selected: range == option,
                  onSelected: (_) => _selectRange(option),
                ),
            ],
          ),
          if (range == HistoryRange.custom && customRange != null) ...[
            const SizedBox(height: 10),
            Text(
              '${_shortDate(customRange!.start)} – ${_shortDate(customRange!.end)}',
            ),
          ],
          const SizedBox(height: 18),
          if (historyLoading && history == null)
            const Padding(
              padding: EdgeInsets.all(28),
              child: LoadingWidget(label: 'Loading telemetry history…'),
            )
          else if (historyError != null && history == null)
            StatePanel(
              icon: Icons.cloud_off_outlined,
              title: 'Unable to load history',
              message: historyError!,
              actionLabel: 'Retry',
              onAction: _loadHistory,
            )
          else if (history == null || history!.readings.isEmpty)
            const StatePanel.empty(
              icon: Icons.show_chart,
              title: 'No telemetry history',
              message: 'No sensor readings are available for this range.',
            )
          else ...[
            if (historyError != null) ...[
              _Notice(historyError!),
              const SizedBox(height: 12),
            ],
            _metricSelector(history!),
            const SizedBox(height: 14),
            _TelemetryChart(history: history!, metric: selectedMetric),
            const SizedBox(height: 14),
            _statistics(history!, selectedMetric),
            const Divider(height: 36),
            Text(
              '${history!.total} reading${history!.total == 1 ? '' : 's'} in range',
              style: const TextStyle(fontWeight: FontWeight.w700),
            ),
            const SizedBox(height: 8),
            for (final point in history!.readings) _HistoryTile(point: point),
            if (history!.hasMore) ...[
              const SizedBox(height: 12),
              OutlinedButton.icon(
                onPressed: loadingMore ? null : _loadMore,
                icon: loadingMore
                    ? const SizedBox.square(
                        dimension: 18,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      )
                    : const Icon(Icons.expand_more),
                label: Text(
                  loadingMore
                      ? 'Loading…'
                      : 'Load more (${history!.page}/${history!.pages})',
                ),
              ),
            ],
          ],
        ],
      ),
    ),
  );

  Widget _metricSelector(TelemetryHistory value) {
    final metrics = _availableMetrics(value);
    return SingleChildScrollView(
      scrollDirection: Axis.horizontal,
      child: Row(
        children: [
          for (final metric in metrics)
            Padding(
              padding: const EdgeInsets.only(right: 8),
              child: ChoiceChip(
                key: Key('metric-${metric.name}'),
                label: Text(metric.label),
                selected: selectedMetric == metric,
                onSelected: (_) => setState(() => selectedMetric = metric),
              ),
            ),
        ],
      ),
    );
  }

  Widget _statistics(TelemetryHistory history, SensorMetric metric) {
    final values = history.readings
        .map(metric.valueOf)
        .whereType<double>()
        .toList(growable: false);
    if (values.isEmpty) return const SizedBox.shrink();
    final minimum = values.reduce((a, b) => a < b ? a : b);
    final maximum = values.reduce((a, b) => a > b ? a : b);
    final average = values.reduce((a, b) => a + b) / values.length;
    return Wrap(
      spacing: 16,
      runSpacing: 8,
      children: [
        _stat('Latest', values.first, metric.unit),
        _stat('Minimum', minimum, metric.unit),
        _stat('Maximum', maximum, metric.unit),
        _stat('Average', average, metric.unit),
      ],
    );
  }

  Widget _stat(String label, double value, String unit) =>
      Chip(label: Text('$label: ${cleanNumber(value)} $unit'));

  List<SensorMetric> _availableMetrics(TelemetryHistory value) => [
    for (final metric in SensorMetric.values)
      if (value.readings.any((point) => metric.valueOf(point) != null)) metric,
  ];

  String _shortDate(DateTime value) =>
      '${value.year}-${value.month.toString().padLeft(2, '0')}-'
      '${value.day.toString().padLeft(2, '0')}';
}

class _TelemetryChart extends StatelessWidget {
  final TelemetryHistory history;
  final SensorMetric metric;

  const _TelemetryChart({required this.history, required this.metric});

  @override
  Widget build(BuildContext context) {
    final points = history.readings.reversed.toList(growable: false);
    final spots = <FlSpot>[];
    for (var index = 0; index < points.length; index++) {
      final value = metric.valueOf(points[index]);
      spots.add(
        value == null ? FlSpot.nullSpot : FlSpot(index.toDouble(), value),
      );
    }
    final multiDay = const {
      HistoryRange.last7Days,
      HistoryRange.last30Days,
      HistoryRange.custom,
    }.contains(history.range);
    return Semantics(
      label: '${metric.label} history chart with actual backend readings',
      child: SizedBox(
        height: 260,
        child: LineChart(
          LineChartData(
            minY: metric == SensorMetric.wasteLevel ? 0 : null,
            maxY: metric == SensorMetric.wasteLevel ? 100 : null,
            gridData: const FlGridData(show: true),
            borderData: FlBorderData(show: true),
            lineTouchData: const LineTouchData(enabled: true),
            titlesData: FlTitlesData(
              topTitles: const AxisTitles(
                sideTitles: SideTitles(showTitles: false),
              ),
              rightTitles: const AxisTitles(
                sideTitles: SideTitles(showTitles: false),
              ),
              leftTitles: AxisTitles(
                axisNameWidget: Text(metric.unit),
                sideTitles: const SideTitles(
                  showTitles: true,
                  reservedSize: 42,
                ),
              ),
              bottomTitles: AxisTitles(
                sideTitles: SideTitles(
                  showTitles: true,
                  reservedSize: 32,
                  interval: points.length <= 2 ? 1 : (points.length - 1) / 2,
                  getTitlesWidget: (value, meta) {
                    final index = value.round();
                    if (index < 0 || index >= points.length) {
                      return const SizedBox.shrink();
                    }
                    return SideTitleWidget(
                      meta: meta,
                      child: Text(
                        chartTime(points[index].recordedAt, multiDay: multiDay),
                        style: const TextStyle(fontSize: 10),
                      ),
                    );
                  },
                ),
              ),
            ),
            lineBarsData: [
              LineChartBarData(
                spots: spots,
                color: emerald,
                barWidth: 3,
                isCurved: false,
                dotData: const FlDotData(show: true),
                belowBarData: BarAreaData(
                  show: true,
                  color: mint.withValues(alpha: 0.3),
                ),
              ),
            ],
          ),
          duration: const Duration(milliseconds: 250),
        ),
      ),
    );
  }
}

class _HistoryTile extends StatelessWidget {
  final TelemetryPoint point;

  const _HistoryTile({required this.point});

  @override
  Widget build(BuildContext context) {
    final values = <String>[
      if (point.wasteLevel != null) '${cleanNumber(point.wasteLevel!)}%',
      if (point.ultrasonicDistance != null)
        '${cleanNumber(point.ultrasonicDistance!)} cm',
      if (point.temperature != null) '${cleanNumber(point.temperature!)} °C',
      if (point.turbidity != null) '${cleanNumber(point.turbidity!)} NTU',
      if (point.flowRate != null) '${cleanNumber(point.flowRate!)} L/min',
      if (point.gasValue != null) 'Gas ${cleanNumber(point.gasValue!)} raw',
    ];
    return ListTile(
      contentPadding: EdgeInsets.zero,
      leading: const Icon(Icons.timeline, color: emerald),
      title: Text(readingTime(point.recordedAt)),
      subtitle: Text(
        values.isEmpty ? 'No valid sensor values' : values.join(' · '),
      ),
      trailing: Text(point.condition),
    );
  }
}

class _StatusChip extends StatelessWidget {
  final String status;

  const _StatusChip(this.status);

  @override
  Widget build(BuildContext context) => Chip(
    avatar: Icon(
      status == 'ONLINE' || status == 'NORMAL'
          ? Icons.check_circle_outline
          : Icons.info_outline,
      size: 18,
    ),
    label: Text(status),
  );
}

class _InlineState extends StatelessWidget {
  final IconData icon;
  final String text;

  const _InlineState({required this.icon, required this.text});

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(12),
    decoration: BoxDecoration(
      color: const Color(0xFFFFF3CD),
      borderRadius: BorderRadius.circular(12),
    ),
    child: Row(
      children: [
        Icon(icon, color: const Color(0xFF805600)),
        const SizedBox(width: 10),
        Expanded(child: Text(text)),
      ],
    ),
  );
}

class _Notice extends StatelessWidget {
  final String message;

  const _Notice(this.message);

  @override
  Widget build(BuildContext context) => Material(
    color: const Color(0xFFFFF3CD),
    borderRadius: BorderRadius.circular(12),
    child: Padding(
      padding: const EdgeInsets.all(12),
      child: Row(
        children: [
          const Icon(Icons.info_outline, color: Color(0xFF805600)),
          const SizedBox(width: 10),
          Expanded(child: Text(message)),
        ],
      ),
    ),
  );
}

extension<T> on List<T> {
  T? get firstOrNull => isEmpty ? null : first;
}
