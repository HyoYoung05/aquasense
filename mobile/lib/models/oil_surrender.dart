String surrenderStatusLabel(String value) =>
    switch (value.trim().toUpperCase()) {
      'UNDER_REVIEW' => 'UNDER REVIEW',
      final code when code.isNotEmpty => code.replaceAll('_', ' '),
      _ => 'UNKNOWN',
    };

String surrenderStatusMessage(String value) => switch (value
    .trim()
    .toUpperCase()) {
  'PENDING' => 'Your submission is waiting for Barangay review.',
  'UNDER_REVIEW' => 'Barangay personnel are reviewing your surrender evidence.',
  'APPROVED' => 'Your oil surrender has been approved.',
  'REJECTED' =>
    'Your oil surrender was not approved. Review the remarks below.',
  _ => 'This submission has a status added by the server.',
};

enum SurrenderStatusFilter {
  all(null, 'All'),
  pending('PENDING', 'Pending'),
  underReview('UNDER_REVIEW', 'Under Review'),
  approved('APPROVED', 'Approved'),
  rejected('REJECTED', 'Rejected');

  final String? apiValue;
  final String label;
  const SurrenderStatusFilter(this.apiValue, this.label);
}

class SurrenderPhoto {
  final int id;
  final DateTime uploadedAt;

  const SurrenderPhoto({required this.id, required this.uploadedAt});

  factory SurrenderPhoto.fromJson(Map<String, dynamic> json) {
    final id = json['id'];
    final uploadedAt = DateTime.tryParse(json['uploaded_at'] as String? ?? '');
    if (id is! num || uploadedAt == null) {
      throw const FormatException('Invalid surrender photo.');
    }
    return SurrenderPhoto(id: id.toInt(), uploadedAt: uploadedAt);
  }
}

class OilSurrender {
  final int id;
  final String transactionCode;
  final String submissionUuid;
  final String businessName;
  final String? greaseTrapName;
  final double quantity;
  final String unit;
  final String? notes;
  final DateTime submittedAt;
  final String status;
  final String verificationStatus;
  final String? reviewRemarks;
  final DateTime? reviewedAt;
  final DateTime? approvedAt;
  final DateTime? rejectedAt;
  final SurrenderPhoto? photo;

  const OilSurrender({
    required this.id,
    required this.transactionCode,
    required this.submissionUuid,
    required this.businessName,
    required this.greaseTrapName,
    required this.quantity,
    required this.unit,
    required this.notes,
    required this.submittedAt,
    required this.status,
    required this.verificationStatus,
    required this.reviewRemarks,
    required this.reviewedAt,
    required this.approvedAt,
    required this.rejectedAt,
    required this.photo,
  });

  factory OilSurrender.fromJson(Map<String, dynamic> json) {
    final id = json['id'];
    final quantity = json['oil_quantity'];
    final submittedAt = DateTime.tryParse(
      json['submitted_at'] as String? ?? '',
    );
    final photo = json['photo'];
    if (id is! num ||
        quantity is! num ||
        submittedAt == null ||
        json['transaction_code'] is! String ||
        json['submission_uuid'] is! String ||
        json['business_name'] is! String ||
        json['oil_unit'] is! String ||
        json['status'] is! String ||
        json['verification_status'] is! String ||
        (photo != null && photo is! Map<String, dynamic>)) {
      throw const FormatException('Invalid oil surrender.');
    }
    return OilSurrender(
      id: id.toInt(),
      transactionCode: (json['transaction_code'] as String).trim(),
      submissionUuid: json['submission_uuid'] as String,
      businessName: json['business_name'] as String,
      greaseTrapName: _text(json['grease_trap_name']),
      quantity: quantity.toDouble(),
      unit: json['oil_unit'] as String,
      notes: _text(json['notes']),
      submittedAt: submittedAt,
      status: (json['status'] as String).trim().toUpperCase(),
      verificationStatus: (json['verification_status'] as String)
          .trim()
          .toUpperCase(),
      reviewRemarks: _text(json['review_remarks']),
      reviewedAt: _date(json['reviewed_at']),
      approvedAt: _date(json['approved_at']),
      rejectedAt: _date(json['rejected_at']),
      photo: photo == null ? null : SurrenderPhoto.fromJson(photo),
    );
  }
}

class OilSurrenderHistory {
  final List<OilSurrender> items;
  const OilSurrenderHistory(this.items);

  int count(String status) =>
      items.where((item) => item.status == status.toUpperCase()).length;

  OilSurrender? get latest => items.isEmpty ? null : items.first;

  factory OilSurrenderHistory.fromJson(Map<String, dynamic> json) {
    final values = json['surrenders'];
    if (values is! List) {
      throw const FormatException('Invalid oil surrender history.');
    }
    final records = <OilSurrender>[];
    for (final value in values) {
      if (value is! Map<String, dynamic>) {
        throw const FormatException('Invalid oil surrender history record.');
      }
      records.add(OilSurrender.fromJson(value));
    }
    return OilSurrenderHistory(records);
  }
}

class SurrenderTrapOption {
  final int id;
  final String name;
  final String businessName;
  const SurrenderTrapOption({
    required this.id,
    required this.name,
    required this.businessName,
  });
}

class SurrenderSubmissionResult {
  final OilSurrender surrender;
  final bool idempotentReplay;
  const SurrenderSubmissionResult({
    required this.surrender,
    required this.idempotentReplay,
  });
}

String? _text(Object? value) {
  if (value == null) return null;
  if (value is! String) throw const FormatException('Invalid text value.');
  final trimmed = value.trim();
  return trimmed.isEmpty ? null : trimmed;
}

DateTime? _date(Object? value) {
  if (value == null) return null;
  if (value is! String) throw const FormatException('Invalid timestamp.');
  final parsed = DateTime.tryParse(value);
  if (parsed == null) throw const FormatException('Invalid timestamp.');
  return parsed;
}
