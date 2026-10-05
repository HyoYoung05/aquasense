class OilSurrenderRecord {
  final int id;
  final String transactionCode;
  final double quantity;
  final String unit;
  final String status;
  final DateTime submittedAt;

  const OilSurrenderRecord({
    required this.id,
    required this.transactionCode,
    required this.quantity,
    required this.unit,
    required this.status,
    required this.submittedAt,
  });

  factory OilSurrenderRecord.fromJson(Map<String, dynamic> json) {
    final id = json['id'];
    final code = json['transaction_code'];
    final quantity = json['oil_quantity'];
    final unit = json['oil_unit'];
    final status = json['status'];
    final submittedAt = json['submitted_at'];
    if (id is! num ||
        code is! String ||
        quantity is! num ||
        unit is! String ||
        status is! String ||
        submittedAt is! String) {
      throw const FormatException('Invalid oil surrender summary.');
    }
    final parsedTime = DateTime.tryParse(submittedAt);
    if (parsedTime == null) {
      throw const FormatException('Invalid oil surrender time.');
    }
    return OilSurrenderRecord(
      id: id.toInt(),
      transactionCode: code,
      quantity: quantity.toDouble(),
      unit: unit,
      status: status,
      submittedAt: parsedTime,
    );
  }
}

class OilSurrenderSummary {
  final List<OilSurrenderRecord> records;

  const OilSurrenderSummary(this.records);

  OilSurrenderRecord? get latest => records.isEmpty ? null : records.first;

  int get pendingCount => records
      .where(
        (record) =>
            record.status == 'PENDING' || record.status == 'UNDER_REVIEW',
      )
      .length;

  factory OilSurrenderSummary.fromJson(Map<String, dynamic> json) {
    final values = json['surrenders'];
    if (values is! List) {
      throw const FormatException('Invalid oil surrender list.');
    }
    final records = <OilSurrenderRecord>[];
    for (final value in values) {
      if (value is! Map<String, dynamic>) {
        throw const FormatException('Invalid oil surrender record.');
      }
      records.add(OilSurrenderRecord.fromJson(value));
    }
    return OilSurrenderSummary(records);
  }
}
