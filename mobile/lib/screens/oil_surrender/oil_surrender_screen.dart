import 'package:flutter/material.dart';

import '../../models/oil_surrender.dart';
import '../../services/api_service.dart';
import '../../services/auth_service.dart';
import '../../services/oil_surrender_service.dart';
import '../../theme/app_theme.dart';
import '../../utils/formatters.dart';
import '../../widgets/loading_widget.dart';
import '../../widgets/state_panel.dart';
import 'new_surrender_screen.dart';
import 'oil_surrender_detail_screen.dart';

class OilSurrenderScreen extends StatefulWidget {
  final AuthService auth;
  final List<SurrenderTrapOption> traps;
  final int establishmentCount;
  final OilSurrenderRepository? repository;
  final EvidencePicker? evidencePicker;
  final Future<void> Function()? onSubmissionSuccess;

  const OilSurrenderScreen({
    super.key,
    required this.auth,
    required this.traps,
    required this.establishmentCount,
    this.repository,
    this.evidencePicker,
    this.onSubmissionSuccess,
  });

  @override
  State<OilSurrenderScreen> createState() => _OilSurrenderScreenState();
}

class _OilSurrenderScreenState extends State<OilSurrenderScreen> {
  late final OilSurrenderRepository _repository;
  SurrenderStatusFilter _filter = SurrenderStatusFilter.all;
  OilSurrenderHistory? _history;
  OilSurrenderHistory? _overviewHistory;
  String? _error;
  String? _refreshError;
  bool _loading = false;

  @override
  void initState() {
    super.initState();
    _repository = widget.repository ?? OilSurrenderService(widget.auth);
    _load();
  }

  Future<void> _load({bool refresh = false}) async {
    if (_loading) return;
    setState(() {
      _loading = true;
      _error = null;
      _refreshError = null;
    });
    try {
      final value = await _repository.loadHistory(_filter);
      if (mounted) {
        setState(() {
          _history = value;
          if (_filter == SurrenderStatusFilter.all) _overviewHistory = value;
        });
      }
    } on ApiException catch (exception) {
      if (mounted && exception.type != ApiErrorType.unauthorized) {
        setState(() {
          if (refresh && _history != null) {
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

  Future<void> _selectFilter(SurrenderStatusFilter filter) async {
    if (_filter == filter) return;
    setState(() {
      _filter = filter;
      _history = null;
    });
    await _load();
  }

  Future<void> _newSubmission() async {
    final created = await Navigator.of(context).push<OilSurrender>(
      MaterialPageRoute(
        builder: (_) => NewSurrenderScreen(
          repository: _repository,
          traps: widget.traps,
          establishmentCount: widget.establishmentCount,
          evidencePicker: widget.evidencePicker,
        ),
      ),
    );
    if (created == null || !mounted) return;
    setState(() => _filter = SurrenderStatusFilter.all);
    await _load(refresh: _history != null);
    await widget.onSubmissionSuccess?.call();
  }

  Future<void> _openDetail(OilSurrender item) async {
    await Navigator.of(context).push<void>(
      MaterialPageRoute(
        builder: (_) => OilSurrenderDetailScreen(
          surrenderId: item.id,
          repository: _repository,
        ),
      ),
    );
    if (mounted) await _load(refresh: true);
  }

  @override
  Widget build(BuildContext context) {
    if (_loading && _history == null) {
      return const LoadingWidget(label: 'Loading oil surrender history…');
    }
    if (_error != null && _history == null) {
      return StatePanel(
        icon: Icons.cloud_off_outlined,
        title: 'Unable to load oil surrenders',
        message: _error!,
        actionLabel: 'Retry',
        onAction: _load,
      );
    }

    final history = _history ?? const OilSurrenderHistory([]);
    final overview = _overviewHistory ?? history;
    final pending = overview.items
        .where((item) => item.status == 'PENDING')
        .length;
    return RefreshIndicator(
      onRefresh: () => _load(refresh: true),
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 32),
        children: [
          Center(
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 820),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  _OverviewCard(
                    pendingCount: pending,
                    latest: overview.latest,
                    onNew: _newSubmission,
                  ),
                  const SizedBox(height: 16),
                  const _HybridVerificationCard(),
                  const SizedBox(height: 20),
                  Text(
                    'Submission history',
                    style: Theme.of(context).textTheme.titleLarge?.copyWith(
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  const SizedBox(height: 10),
                  SingleChildScrollView(
                    scrollDirection: Axis.horizontal,
                    child: Row(
                      children: [
                        for (final filter in SurrenderStatusFilter.values)
                          Padding(
                            padding: const EdgeInsets.only(right: 8),
                            child: ChoiceChip(
                              label: Text(filter.label),
                              selected: _filter == filter,
                              onSelected: _loading
                                  ? null
                                  : (_) => _selectFilter(filter),
                            ),
                          ),
                      ],
                    ),
                  ),
                  if (_refreshError != null) ...[
                    const SizedBox(height: 12),
                    MaterialBanner(
                      content: Text(_refreshError!),
                      actions: [
                        TextButton(
                          onPressed: _load,
                          child: const Text('Retry'),
                        ),
                      ],
                    ),
                  ],
                  const SizedBox(height: 12),
                  if (history.items.isEmpty)
                    Card(
                      child: Padding(
                        padding: const EdgeInsets.all(24),
                        child: Column(
                          children: [
                            const Icon(Icons.oil_barrel_outlined, size: 42),
                            const SizedBox(height: 12),
                            Text(
                              _filter == SurrenderStatusFilter.all
                                  ? 'No oil surrender submissions yet.'
                                  : 'No ${_filter.label.toLowerCase()} submissions.',
                              textAlign: TextAlign.center,
                            ),
                          ],
                        ),
                      ),
                    )
                  else
                    for (final item in history.items)
                      _SurrenderCard(
                        item: item,
                        onTap: () => _openDetail(item),
                      ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _OverviewCard extends StatelessWidget {
  final int pendingCount;
  final OilSurrender? latest;
  final VoidCallback onNew;

  const _OverviewCard({
    required this.pendingCount,
    required this.latest,
    required this.onNew,
  });

  @override
  Widget build(BuildContext context) => Card(
    color: forest,
    child: Padding(
      padding: const EdgeInsets.all(22),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              const Expanded(
                child: Text(
                  'Oil Surrender',
                  style: TextStyle(
                    color: Colors.white,
                    fontSize: 24,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
              Chip(label: Text('$pendingCount pending')),
            ],
          ),
          const SizedBox(height: 8),
          Text(
            latest == null
                ? 'Submit used cooking oil evidence for Barangay review.'
                : 'Latest: ${latest!.transactionCode} · '
                      '${surrenderStatusLabel(latest!.status)}',
            style: const TextStyle(color: Colors.white70),
          ),
          const SizedBox(height: 18),
          FilledButton.icon(
            onPressed: onNew,
            icon: const Icon(Icons.add_a_photo_outlined),
            label: const Text('New Surrender'),
          ),
        ],
      ),
    ),
  );
}

class _HybridVerificationCard extends StatelessWidget {
  const _HybridVerificationCard();

  @override
  Widget build(BuildContext context) => const Card(
    child: ListTile(
      leading: Icon(Icons.fact_check_outlined, color: emerald),
      title: Text(
        'Hybrid Verification',
        style: TextStyle(fontWeight: FontWeight.w700),
      ),
      subtitle: Text(
        'Barangay personnel review your submission, photo evidence, and available IoT sensor records before making the final decision.',
      ),
    ),
  );
}

class _SurrenderCard extends StatelessWidget {
  final OilSurrender item;
  final VoidCallback onTap;
  const _SurrenderCard({required this.item, required this.onTap});

  @override
  Widget build(BuildContext context) => Card(
    margin: const EdgeInsets.only(bottom: 10),
    child: InkWell(
      borderRadius: BorderRadius.circular(12),
      onTap: onTap,
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Row(
          children: [
            const CircleAvatar(child: Icon(Icons.oil_barrel_outlined)),
            const SizedBox(width: 14),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    item.transactionCode,
                    style: const TextStyle(fontWeight: FontWeight.w800),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    '${cleanNumber(item.quantity, decimals: 3)} ${item.unit}',
                  ),
                  Text(
                    readingTime(item.submittedAt),
                    style: Theme.of(context).textTheme.bodySmall,
                  ),
                ],
              ),
            ),
            Column(
              crossAxisAlignment: CrossAxisAlignment.end,
              children: [
                _StatusChip(status: item.status),
                const SizedBox(height: 4),
                const Icon(Icons.chevron_right),
              ],
            ),
          ],
        ),
      ),
    ),
  );
}

class SurrenderStatusChip extends StatelessWidget {
  final String status;
  const SurrenderStatusChip({super.key, required this.status});

  @override
  Widget build(BuildContext context) => _StatusChip(status: status);
}

class _StatusChip extends StatelessWidget {
  final String status;
  const _StatusChip({required this.status});

  @override
  Widget build(BuildContext context) {
    final code = status.toUpperCase();
    final color = switch (code) {
      'APPROVED' => Colors.green,
      'REJECTED' => Colors.red,
      'UNDER_REVIEW' => Colors.blue,
      _ => Colors.orange,
    };
    return Chip(
      side: BorderSide(color: color.withValues(alpha: 0.45)),
      backgroundColor: color.withValues(alpha: 0.10),
      label: Text(
        surrenderStatusLabel(status),
        style: TextStyle(color: color.shade700, fontWeight: FontWeight.w700),
      ),
    );
  }
}
