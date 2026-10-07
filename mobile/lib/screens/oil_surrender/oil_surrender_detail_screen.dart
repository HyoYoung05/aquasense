import 'dart:typed_data';

import 'package:flutter/material.dart';

import '../../models/oil_surrender.dart';
import '../../models/incentive_summary.dart';
import '../../services/api_service.dart';
import '../../services/oil_surrender_service.dart';
import '../../services/incentives_service.dart';
import '../../theme/app_theme.dart';
import '../../utils/formatters.dart';
import '../../widgets/loading_widget.dart';
import '../../widgets/state_panel.dart';
import 'oil_surrender_screen.dart';
import '../incentives/incentive_detail_screen.dart';

class OilSurrenderDetailScreen extends StatefulWidget {
  final int surrenderId;
  final OilSurrenderRepository repository;
  final IncentivesRepository? incentivesRepository;

  const OilSurrenderDetailScreen({
    super.key,
    required this.surrenderId,
    required this.repository,
    this.incentivesRepository,
  });

  @override
  State<OilSurrenderDetailScreen> createState() =>
      _OilSurrenderDetailScreenState();
}

class _OilSurrenderDetailScreenState extends State<OilSurrenderDetailScreen> {
  OilSurrender? _item;
  String? _error;
  bool _loading = false;
  Future<Uint8List>? _photo;
  IncentiveTransaction? _incentive;
  String? _incentiveError;
  bool _incentiveLoading = false;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    if (_loading) return;
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final item = await widget.repository.loadDetail(widget.surrenderId);
      if (!mounted) return;
      setState(() {
        _item = item;
        _photo = item.photo == null
            ? null
            : widget.repository.loadPhoto(item.photo!.id);
      });
      if (item.status == 'APPROVED' && widget.incentivesRepository != null) {
        await _loadIncentive(item.transactionCode);
      }
    } on ApiException catch (exception) {
      if (mounted && exception.type != ApiErrorType.unauthorized) {
        setState(() => _error = exception.message);
      }
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _loadIncentive(String surrenderCode) async {
    if (_incentiveLoading || widget.incentivesRepository == null) return;
    setState(() {
      _incentiveLoading = true;
      _incentiveError = null;
    });
    try {
      final summary = await widget.incentivesRepository!.load();
      IncentiveTransaction? match;
      for (final record in summary.transactions) {
        if (record.surrenderCode == surrenderCode) {
          match = record;
          break;
        }
      }
      if (mounted) setState(() => _incentive = match);
    } on ApiException catch (exception) {
      if (mounted && exception.type != ApiErrorType.unauthorized) {
        setState(() => _incentiveError = exception.message);
      }
    } finally {
      if (mounted) setState(() => _incentiveLoading = false);
    }
  }

  void _retryPhoto() {
    final photo = _item?.photo;
    if (photo != null) {
      setState(() => _photo = widget.repository.loadPhoto(photo.id));
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(
      title: const Text('Surrender Details'),
      actions: [
        IconButton(
          onPressed: _loading ? null : _load,
          tooltip: 'Refresh',
          icon: const Icon(Icons.refresh),
        ),
      ],
    ),
    body: _body(),
  );

  Widget _body() {
    if (_loading && _item == null) {
      return const LoadingWidget(label: 'Loading surrender details…');
    }
    if (_error != null && _item == null) {
      return StatePanel(
        icon: Icons.cloud_off_outlined,
        title: 'Unable to load submission',
        message: _error!,
        actionLabel: 'Retry',
        onAction: _load,
      );
    }
    final item = _item!;
    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
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
                          SurrenderStatusChip(status: item.status),
                          const SizedBox(height: 10),
                          Text(
                            item.transactionCode,
                            style: const TextStyle(
                              color: Colors.white,
                              fontSize: 22,
                              fontWeight: FontWeight.w800,
                            ),
                          ),
                          const SizedBox(height: 6),
                          Text(
                            surrenderStatusMessage(item.status),
                            style: const TextStyle(color: Colors.white70),
                          ),
                        ],
                      ),
                    ),
                  ),
                  if (_error != null) ...[
                    const SizedBox(height: 10),
                    Text(
                      _error!,
                      style: TextStyle(
                        color: Theme.of(context).colorScheme.error,
                      ),
                    ),
                  ],
                  const SizedBox(height: 12),
                  _Section(
                    title: 'Submission',
                    children: [
                      _Detail(
                        'Quantity',
                        '${cleanNumber(item.quantity, decimals: 3)} ${item.unit}',
                      ),
                      _Detail('Submitted', readingTime(item.submittedAt)),
                      _Detail('Business', item.businessName),
                      _Detail(
                        'Grease trap',
                        item.greaseTrapName ?? 'Not specified',
                      ),
                      _Detail('Owner notes', item.notes ?? 'None'),
                    ],
                  ),
                  const SizedBox(height: 12),
                  _Section(
                    title: 'Barangay review',
                    children: [
                      _Detail(
                        'Current status',
                        surrenderStatusLabel(item.status),
                      ),
                      _Detail(
                        'Verification status',
                        surrenderStatusLabel(item.verificationStatus),
                      ),
                      _Detail(
                        'Reviewed by',
                        item.reviewedAt == null
                            ? 'Awaiting Barangay review'
                            : 'Barangay',
                      ),
                      if (item.reviewedAt != null)
                        _Detail('Review date', readingTime(item.reviewedAt!)),
                      if (item.approvedAt != null)
                        _Detail('Approved', readingTime(item.approvedAt!)),
                      if (item.rejectedAt != null)
                        _Detail('Rejected', readingTime(item.rejectedAt!)),
                      _Detail(
                        'Review remarks',
                        item.reviewRemarks ?? 'No owner-visible remarks yet.',
                      ),
                    ],
                  ),
                  if (item.status == 'APPROVED' &&
                      widget.incentivesRepository != null) ...[
                    const SizedBox(height: 12),
                    _IncentiveLinkCard(
                      loading: _incentiveLoading,
                      incentive: _incentive,
                      error: _incentiveError,
                      onRetry: () => _loadIncentive(item.transactionCode),
                      onOpen: _incentive == null
                          ? null
                          : () => Navigator.of(context).push<void>(
                              MaterialPageRoute(
                                builder: (_) => IncentiveDetailScreen(
                                  transaction: _incentive!,
                                ),
                              ),
                            ),
                    ),
                  ],
                  const SizedBox(height: 12),
                  _Section(
                    title: 'Photo evidence',
                    children: [
                      if (_photo == null)
                        const Text('No photo is available for this record.')
                      else
                        FutureBuilder<Uint8List>(
                          future: _photo,
                          builder: (context, snapshot) {
                            if (snapshot.connectionState ==
                                ConnectionState.waiting) {
                              return const SizedBox(
                                height: 180,
                                child: Center(
                                  child: CircularProgressIndicator(),
                                ),
                              );
                            }
                            if (snapshot.hasError || !snapshot.hasData) {
                              return Column(
                                children: [
                                  const Text(
                                    'The protected photo could not be loaded.',
                                  ),
                                  TextButton.icon(
                                    onPressed: _retryPhoto,
                                    icon: const Icon(Icons.refresh),
                                    label: const Text('Retry photo'),
                                  ),
                                ],
                              );
                            }
                            return ClipRRect(
                              borderRadius: BorderRadius.circular(12),
                              child: Image.memory(
                                snapshot.data!,
                                fit: BoxFit.contain,
                                errorBuilder: (_, _, _) => const Padding(
                                  padding: EdgeInsets.all(24),
                                  child: Text('Photo preview unavailable.'),
                                ),
                              ),
                            );
                          },
                        ),
                    ],
                  ),
                  const SizedBox(height: 12),
                  const Card(
                    child: ListTile(
                      leading: Icon(Icons.fact_check_outlined, color: emerald),
                      title: Text(
                        'Hybrid Verification',
                        style: TextStyle(fontWeight: FontWeight.w700),
                      ),
                      subtitle: Text(
                        'Barangay personnel may compare the Owner submission and photo with available IoT sensor records. The final decision is made by Barangay staff.',
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
}

class _IncentiveLinkCard extends StatelessWidget {
  final bool loading;
  final IncentiveTransaction? incentive;
  final String? error;
  final VoidCallback onRetry;
  final VoidCallback? onOpen;

  const _IncentiveLinkCard({
    required this.loading,
    required this.incentive,
    required this.error,
    required this.onRetry,
    required this.onOpen,
  });

  @override
  Widget build(BuildContext context) {
    if (loading) {
      return const Card(
        child: ListTile(
          leading: CircularProgressIndicator(),
          title: Text('Checking rice incentive…'),
        ),
      );
    }
    if (error != null) {
      return Card(
        child: ListTile(
          leading: const Icon(Icons.cloud_off_outlined),
          title: const Text('Incentive status unavailable'),
          subtitle: Text(error!),
          trailing: TextButton(onPressed: onRetry, child: const Text('Retry')),
        ),
      );
    }
    if (incentive == null) {
      return const Card(
        child: ListTile(
          leading: Icon(Icons.schedule_outlined, color: Colors.orange),
          title: Text(
            'Awaiting Processing',
            style: TextStyle(fontWeight: FontWeight.w700),
          ),
          subtitle: Text('Approved. Incentive processing is pending.'),
        ),
      );
    }
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            const Text(
              'Rice Incentive Available',
              style: TextStyle(
                color: forest,
                fontSize: 18,
                fontWeight: FontWeight.w800,
              ),
            ),
            const SizedBox(height: 8),
            Text(
              '${cleanQuantity(incentive!.rewardQuantity)} ${incentive!.rewardUnit}',
            ),
            Text(incentiveStatusLabel(incentive!.status)),
            const SizedBox(height: 8),
            OutlinedButton.icon(
              onPressed: onOpen,
              icon: const Icon(Icons.open_in_new),
              label: const Text('View Incentive'),
            ),
          ],
        ),
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
          width: 126,
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
