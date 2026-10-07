import 'package:flutter/material.dart';

import '../../models/incentive_summary.dart';
import '../../services/api_service.dart';
import '../../services/auth_service.dart';
import '../../services/incentives_service.dart';
import '../../theme/app_theme.dart';
import '../../utils/formatters.dart';
import '../../widgets/loading_widget.dart';
import '../../widgets/state_panel.dart';
import 'incentive_detail_screen.dart';

class IncentivesScreen extends StatefulWidget {
  final AuthService auth;
  final IncentivesRepository? repository;

  const IncentivesScreen({super.key, required this.auth, this.repository});

  @override
  State<IncentivesScreen> createState() => _IncentivesScreenState();
}

class _IncentivesScreenState extends State<IncentivesScreen>
    with WidgetsBindingObserver {
  late final IncentivesRepository _repository;
  IncentiveSummary? _summary;
  IncentiveStatusFilter _filter = IncentiveStatusFilter.all;
  String? _error;
  String? _refreshError;
  bool _loading = false;
  bool _wasBackgrounded = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _repository = widget.repository ?? IncentivesService(widget.auth);
    _load();
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.paused ||
        state == AppLifecycleState.inactive ||
        state == AppLifecycleState.detached) {
      _wasBackgrounded = true;
    } else if (state == AppLifecycleState.resumed && _wasBackgrounded) {
      _wasBackgrounded = false;
      _load(refresh: _summary != null);
    }
  }

  Future<void> _load({bool refresh = false}) async {
    if (_loading) return;
    setState(() {
      _loading = true;
      _error = null;
      _refreshError = null;
    });
    try {
      final value = await _repository.load();
      if (mounted) {
        setState(() => _summary = value);
      }
    } on ApiException catch (exception) {
      if (mounted && exception.type != ApiErrorType.unauthorized) {
        setState(() {
          if ((refresh || _summary != null) && _summary != null) {
            _refreshError = exception.message;
          } else {
            _error = exception.message;
          }
        });
      }
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _open(IncentiveTransaction item) async {
    await Navigator.of(context).push<void>(
      MaterialPageRoute(
        builder: (_) => IncentiveDetailScreen(transaction: item),
      ),
    );
    if (mounted) await _load(refresh: true);
  }

  @override
  Widget build(BuildContext context) {
    if (_loading && _summary == null) {
      return const LoadingWidget(label: 'Loading rice incentives…');
    }
    if (_error != null && _summary == null) {
      return StatePanel(
        icon: Icons.cloud_off_outlined,
        title: 'Unable to load incentive information',
        message: _error!,
        actionLabel: 'Retry',
        onAction: _load,
      );
    }
    final summary =
        _summary ?? const IncentiveSummary(units: [], transactions: []);
    final records = summary.filtered(_filter);
    return RefreshIndicator(
      onRefresh: () => _load(refresh: true),
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 32),
        children: [
          Center(
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 860),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Row(
                    children: [
                      Expanded(
                        child: Text(
                          'Rice incentive summary',
                          style: Theme.of(context).textTheme.titleLarge
                              ?.copyWith(fontWeight: FontWeight.w800),
                        ),
                      ),
                      IconButton(
                        onPressed: _loading ? null : () => _load(refresh: true),
                        tooltip: 'Refresh incentives',
                        icon: const Icon(Icons.refresh),
                      ),
                    ],
                  ),
                  const SizedBox(height: 10),
                  if (summary.units.isEmpty)
                    const StatePanel.empty(
                      icon: Icons.card_giftcard_outlined,
                      title: 'No incentive records yet.',
                      message:
                          'Approved oil surrenders will appear here after the backend processes an incentive.',
                    )
                  else
                    LayoutBuilder(
                      builder: (context, constraints) {
                        final width = constraints.maxWidth >= 620
                            ? (constraints.maxWidth - 12) / 2
                            : constraints.maxWidth;
                        return Wrap(
                          spacing: 12,
                          runSpacing: 12,
                          children: [
                            for (final unit in summary.units)
                              SizedBox(
                                width: width,
                                child: _UnitSummaryCard(unit: unit),
                              ),
                          ],
                        );
                      },
                    ),
                  if (summary.latest case final latest?) ...[
                    const SizedBox(height: 12),
                    Card(
                      child: ListTile(
                        leading: const Icon(
                          Icons.card_giftcard_outlined,
                          color: emerald,
                        ),
                        title: const Text(
                          'Latest Reward',
                          style: TextStyle(fontWeight: FontWeight.w700),
                        ),
                        subtitle: Text(
                          '${cleanQuantity(latest.rewardQuantity)} ${latest.rewardUnit} · '
                          '${incentiveStatusLabel(latest.status)}',
                        ),
                        trailing: const Icon(Icons.chevron_right),
                        onTap: () => _open(latest),
                      ),
                    ),
                  ],
                  if (_refreshError != null) ...[
                    const SizedBox(height: 12),
                    MaterialBanner(
                      content: Text(
                        'Unable to refresh. Showing the last loaded data. $_refreshError',
                      ),
                      actions: [
                        TextButton(
                          onPressed: () => _load(refresh: true),
                          child: const Text('Retry'),
                        ),
                      ],
                    ),
                  ],
                  const SizedBox(height: 24),
                  Text(
                    'Incentive history',
                    style: Theme.of(context).textTheme.titleLarge?.copyWith(
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  const SizedBox(height: 10),
                  SingleChildScrollView(
                    scrollDirection: Axis.horizontal,
                    child: Row(
                      children: [
                        for (final filter in IncentiveStatusFilter.values)
                          Padding(
                            padding: const EdgeInsets.only(right: 8),
                            child: ChoiceChip(
                              label: Text(filter.label),
                              selected: _filter == filter,
                              onSelected: (_) =>
                                  setState(() => _filter = filter),
                            ),
                          ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 12),
                  if (records.isEmpty)
                    StatePanel.empty(
                      icon: Icons.history_outlined,
                      title: _filter == IncentiveStatusFilter.all
                          ? 'No incentive records yet.'
                          : 'No ${_filter.label.toLowerCase()} records',
                      message: _filter == IncentiveStatusFilter.all
                          ? 'Incentive history will appear after the backend processes an approved surrender.'
                          : 'Try another history filter.',
                    )
                  else
                    for (final item in records)
                      _TransactionCard(item: item, onTap: () => _open(item)),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _UnitSummaryCard extends StatelessWidget {
  final IncentiveUnitSummary unit;
  const _UnitSummaryCard({required this.unit});

  @override
  Widget build(BuildContext context) => Card(
    child: Padding(
      padding: const EdgeInsets.all(18),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            unit.unit,
            style: Theme.of(context).textTheme.titleLarge?.copyWith(
              color: forest,
              fontWeight: FontWeight.w800,
            ),
          ),
          const SizedBox(height: 14),
          _Metric(
            label: 'Pending',
            value: unit.pending,
            unit: unit.unit,
            emphasized: true,
          ),
          _Metric(
            label: 'Distributed',
            value: unit.distributed,
            unit: unit.unit,
          ),
          _Metric(label: 'Total earned', value: unit.earned, unit: unit.unit),
        ],
      ),
    ),
  );
}

class _Metric extends StatelessWidget {
  final String label;
  final double value;
  final String unit;
  final bool emphasized;
  const _Metric({
    required this.label,
    required this.value,
    required this.unit,
    this.emphasized = false,
  });

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(bottom: 8),
    child: Row(
      children: [
        Expanded(child: Text(label)),
        Text(
          '${cleanQuantity(value)} $unit',
          style: TextStyle(
            color: emphasized ? emerald : null,
            fontWeight: FontWeight.w800,
          ),
        ),
      ],
    ),
  );
}

class _TransactionCard extends StatelessWidget {
  final IncentiveTransaction item;
  final VoidCallback onTap;
  const _TransactionCard({required this.item, required this.onTap});

  @override
  Widget build(BuildContext context) => Card(
    margin: const EdgeInsets.only(bottom: 10),
    child: InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(12),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Wrap(
              alignment: WrapAlignment.spaceBetween,
              crossAxisAlignment: WrapCrossAlignment.center,
              spacing: 12,
              children: [
                Text(
                  item.transactionCode,
                  style: const TextStyle(fontWeight: FontWeight.w800),
                ),
                IncentiveStatusChip(status: item.status),
              ],
            ),
            const SizedBox(height: 8),
            if (item.surrenderCode != null)
              Text('Surrender: ${item.surrenderCode}'),
            if (item.oilQuantity != null && item.oilUnit != null)
              Text(
                'Oil surrendered: ${cleanQuantity(item.oilQuantity!)} ${item.oilUnit}',
              ),
            Text(
              'Rice reward: ${cleanQuantity(item.rewardQuantity)} ${item.rewardUnit}',
              style: const TextStyle(
                color: forest,
                fontWeight: FontWeight.w700,
              ),
            ),
            Text(
              'Processed: ${readingTime(item.processedAt)}',
              style: Theme.of(context).textTheme.bodySmall,
            ),
            Text(
              'Distributed: ${item.distributedAt == null ? 'Not yet distributed' : readingTime(item.distributedAt!)}',
              style: Theme.of(context).textTheme.bodySmall,
            ),
          ],
        ),
      ),
    ),
  );
}
