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
        earned is! num ||
        distributed is! num ||
        pending is! num) {
      throw const FormatException('Invalid incentive unit summary.');
    }
    return IncentiveUnitSummary(
      unit: unit,
      earned: earned.toDouble(),
      distributed: distributed.toDouble(),
      pending: pending.toDouble(),
    );
  }
}

class IncentiveTransaction {
  final String transactionCode;
  final double quantity;
  final String unit;
  final String status;
  final DateTime processedAt;

  const IncentiveTransaction({
    required this.transactionCode,
    required this.quantity,
    required this.unit,
    required this.status,
    required this.processedAt,
  });

  factory IncentiveTransaction.fromJson(Map<String, dynamic> json) {
    final code = json['transaction_code'];
    final quantity = json['rice_quantity'];
    final unit = json['rice_unit'];
    final status = json['status'];
    final processedAt = json['processed_at'];
    if (code is! String ||
        quantity is! num ||
        unit is! String ||
        status is! String ||
        processedAt is! String) {
      throw const FormatException('Invalid incentive transaction.');
    }
    final parsedTime = DateTime.tryParse(processedAt);
    if (parsedTime == null) {
      throw const FormatException('Invalid incentive time.');
    }
    return IncentiveTransaction(
      transactionCode: code,
      quantity: quantity.toDouble(),
      unit: unit,
      status: status,
      processedAt: parsedTime,
    );
  }
}

class IncentiveSummary {
  final List<IncentiveUnitSummary> units;
  final List<IncentiveTransaction> transactions;

  const IncentiveSummary({required this.units, required this.transactions});

  IncentiveTransaction? get latest =>
      transactions.isEmpty ? null : transactions.first;

  factory IncentiveSummary.fromJson(Map<String, dynamic> json) {
    final summary = json['summary'];
    final transactions = json['transactions'];
    if (summary is! List || transactions is! List) {
      throw const FormatException('Invalid incentive summary.');
    }
    final units = <IncentiveUnitSummary>[];
    for (final value in summary) {
      if (value is! Map<String, dynamic>) {
        throw const FormatException('Invalid incentive unit.');
      }
      units.add(IncentiveUnitSummary.fromJson(value));
    }
    final records = <IncentiveTransaction>[];
    for (final value in transactions) {
      if (value is! Map<String, dynamic>) {
        throw const FormatException('Invalid incentive transaction list.');
      }
      records.add(IncentiveTransaction.fromJson(value));
    }
    return IncentiveSummary(units: units, transactions: records);
  }
}
