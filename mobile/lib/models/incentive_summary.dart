String incentiveStatusLabel(String value) =>
    switch (value.trim().toUpperCase()) {
      'CALCULATED' => 'Pending Distribution',
      'APPROVED_FOR_DISTRIBUTION' => 'Ready for Distribution',
      'DISTRIBUTED' => 'Distributed',
      'CANCELLED' => 'Cancelled',
      final code when code.isNotEmpty => _titleCase(code.replaceAll('_', ' ')),
      _ => 'Unknown',
    };

String incentiveStatusMessage(String value) => switch (value
    .trim()
    .toUpperCase()) {
  'CALCULATED' =>
    'Your reward has been calculated and is awaiting Barangay processing.',
  'APPROVED_FOR_DISTRIBUTION' =>
    'Your reward is ready for Barangay distribution.',
  'DISTRIBUTED' => 'Barangay personnel recorded this reward as distributed.',
  'CANCELLED' => 'This incentive record was cancelled by Barangay personnel.',
  _ => 'This record has a status supplied by the server.',
};

bool incentiveIsPending(String value) => const {
  'CALCULATED',
  'APPROVED_FOR_DISTRIBUTION',
}.contains(value.trim().toUpperCase());

enum IncentiveStatusFilter {
  all('All'),
  pending('Pending Distribution'),
  distributed('Distributed'),
  cancelled('Cancelled');

  final String label;
  const IncentiveStatusFilter(this.label);

  bool matches(IncentiveTransaction item) => switch (this) {
    IncentiveStatusFilter.all => true,
    IncentiveStatusFilter.pending => incentiveIsPending(item.status),
    IncentiveStatusFilter.distributed => item.status == 'DISTRIBUTED',
    IncentiveStatusFilter.cancelled => item.status == 'CANCELLED',
  };
}

class IncentiveUnitSummary {
  final String unit;
  final double earned;
  final double distributed;
  final double pending;

  const IncentiveUnitSummary({
    required this.unit,
    required this.earned,
    required this.distributed,
    required this.pending,
  });

  factory IncentiveUnitSummary.fromJson(Map<String, dynamic> json) {
    final unit = json['unit'];
    final earned = json['earned'];
    final distributed = json['distributed'];
    final pending = json['pending'];
    if (unit is! String ||
        unit.trim().isEmpty ||
        earned is! num ||
        distributed is! num ||
        pending is! num) {
      throw const FormatException('Invalid incentive unit summary.');
    }
    return IncentiveUnitSummary(
      unit: unit.trim(),
      earned: earned.toDouble(),
      distributed: distributed.toDouble(),
      pending: pending.toDouble(),
    );
  }
}

class IncentiveTransaction {
  final String transactionCode;
  final String? surrenderCode;
  final String? businessName;
  final double? oilQuantity;
  final String? oilUnit;
  final double rewardQuantity;
  final String rewardUnit;
  final String status;
  final DateTime processedAt;
  final DateTime? distributedAt;

  const IncentiveTransaction({
    required this.transactionCode,
    required this.surrenderCode,
    required this.businessName,
    required this.oilQuantity,
    required this.oilUnit,
    required this.rewardQuantity,
    required this.rewardUnit,
    required this.status,
    required this.processedAt,
    required this.distributedAt,
  });

  double get quantity => rewardQuantity;
  String get unit => rewardUnit;

  factory IncentiveTransaction.fromJson(Map<String, dynamic> json) {
    final reward = json['rice_quantity'];
    final oilQuantity = json['oil_quantity'];
    if (reward is! num || (oilQuantity != null && oilQuantity is! num)) {
      throw const FormatException('Invalid incentive transaction.');
    }
    return IncentiveTransaction(
      transactionCode: _requiredText(json['transaction_code']),
      surrenderCode: _optionalText(json['surrender_code']),
      businessName: _optionalText(json['business_name']),
      oilQuantity: (oilQuantity as num?)?.toDouble(),
      oilUnit: _optionalText(json['oil_unit']),
      rewardQuantity: reward.toDouble(),
      rewardUnit: _requiredText(json['rice_unit']),
      status: _requiredText(json['status']).toUpperCase(),
      processedAt: _requiredDate(json['processed_at']),
      distributedAt: _optionalDate(json['distributed_at']),
    );
  }
}

class IncentiveSummary {
  final List<IncentiveUnitSummary> units;
  final List<IncentiveTransaction> transactions;

  const IncentiveSummary({required this.units, required this.transactions});

  IncentiveTransaction? get latest =>
      transactions.isEmpty ? null : transactions.first;

  List<IncentiveTransaction> filtered(IncentiveStatusFilter filter) =>
      transactions.where(filter.matches).toList(growable: false);

  factory IncentiveSummary.fromJson(Map<String, dynamic> json) {
    final summary = json['summary'];
    final transactions = json['transactions'];
    if (summary is! List || transactions is! List) {
      throw const FormatException('Invalid incentive summary.');
    }
    final units = <IncentiveUnitSummary>[];
    final records = <IncentiveTransaction>[];
    for (final value in summary) {
      if (value is! Map<String, dynamic>) {
        throw const FormatException('Invalid incentive unit.');
      }
      units.add(IncentiveUnitSummary.fromJson(value));
    }
    for (final value in transactions) {
      if (value is! Map<String, dynamic>) {
        throw const FormatException('Invalid incentive transaction list.');
      }
      records.add(IncentiveTransaction.fromJson(value));
    }
    return IncentiveSummary(units: units, transactions: records);
  }
}

String _requiredText(Object? value) {
  if (value is! String || value.trim().isEmpty) {
    throw const FormatException('Invalid text value.');
  }
  return value.trim();
}

String? _optionalText(Object? value) {
  if (value == null) return null;
  if (value is! String) throw const FormatException('Invalid text value.');
  final text = value.trim();
  return text.isEmpty ? null : text;
}

DateTime _requiredDate(Object? value) {
  if (value is! String) throw const FormatException('Invalid timestamp.');
  final parsed = DateTime.tryParse(value);
  if (parsed == null) throw const FormatException('Invalid timestamp.');
  return parsed;
}

DateTime? _optionalDate(Object? value) =>
    value == null ? null : _requiredDate(value);

String _titleCase(String value) => value
    .toLowerCase()
    .split(' ')
    .map(
      (part) =>
          part.isEmpty ? part : '${part[0].toUpperCase()}${part.substring(1)}',
    )
    .join(' ');
