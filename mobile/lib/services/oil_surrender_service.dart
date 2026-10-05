import 'dart:math';
import 'package:flutter/foundation.dart';
import 'package:image_picker/image_picker.dart';

import '../models/oil_surrender.dart';
import 'api_service.dart';
import 'auth_service.dart';

const int surrenderPhotoLimitBytes = 5 * 1024 * 1024;
const Set<String> surrenderPhotoExtensions = {'jpg', 'jpeg', 'png', 'webp'};

class EvidencePhoto {
  final XFile file;
  final int size;
  final String extension;
  const EvidencePhoto({
    required this.file,
    required this.size,
    required this.extension,
  });

  String get filename => file.name;
  Future<Uint8List> previewBytes() => file.readAsBytes();

  static Future<EvidencePhoto> validate(XFile file) async {
    final extension = file.name.contains('.')
        ? file.name.split('.').last.toLowerCase()
        : '';
    if (!surrenderPhotoExtensions.contains(extension)) {
      throw const ApiException(
        'Photo evidence must be a JPEG, PNG, or WEBP image.',
        type: ApiErrorType.validationError,
      );
    }
    final size = await file.length();
    if (size < 1) {
      throw const ApiException(
        'Please choose a valid photo.',
        type: ApiErrorType.validationError,
      );
    }
    if (size > surrenderPhotoLimitBytes) {
      throw const ApiException(
        'The selected image is too large. Please choose a smaller image.',
        type: ApiErrorType.validationError,
      );
    }
    return EvidencePhoto(file: file, size: size, extension: extension);
  }
}

abstract class EvidencePicker {
  Future<XFile?> pick(ImageSource source);
}

class ImagePickerEvidence implements EvidencePicker {
  final ImagePicker picker;
  ImagePickerEvidence({ImagePicker? picker}) : picker = picker ?? ImagePicker();

  @override
  Future<XFile?> pick(ImageSource source) => picker.pickImage(
    source: source,
    maxWidth: 2048,
    maxHeight: 2048,
    imageQuality: 88,
    requestFullMetadata: false,
  );
}

abstract class OilSurrenderRepository {
  Future<OilSurrenderHistory> loadHistory(SurrenderStatusFilter filter);
  Future<OilSurrender> loadDetail(int id);
  Future<Uint8List> loadPhoto(int photoId);
  Future<SurrenderSubmissionResult> submit({
    required String submissionUuid,
    required double quantity,
    required String unit,
    required int? greaseTrapId,
    required String notes,
    required EvidencePhoto photo,
  });
}

class OilSurrenderService implements OilSurrenderRepository {
  final AuthService auth;
  const OilSurrenderService(this.auth);

  @override
  Future<OilSurrenderHistory> loadHistory(SurrenderStatusFilter filter) async {
    final query = filter.apiValue == null
        ? 'oil-surrenders.php'
        : 'oil-surrenders.php?status=${filter.apiValue}';
    try {
      return OilSurrenderHistory.fromJson(await auth.get(query));
    } on FormatException {
      throw const ApiException(
        'The server returned unreadable oil surrender history.',
        type: ApiErrorType.serverError,
      );
    }
  }

  @override
  Future<OilSurrender> loadDetail(int id) async {
    try {
      final data = await auth.get('oil-surrender.php?id=$id');
      return OilSurrender.fromJson(data['surrender'] as Map<String, dynamic>);
    } on FormatException {
      throw const ApiException(
        'The server returned unreadable oil surrender information.',
        type: ApiErrorType.serverError,
      );
    }
  }

  @override
  Future<Uint8List> loadPhoto(int photoId) =>
      auth.getBytes('oil-surrender-photo.php?id=$photoId');

  @override
  Future<SurrenderSubmissionResult> submit({
    required String submissionUuid,
    required double quantity,
    required String unit,
    required int? greaseTrapId,
    required String notes,
    required EvidencePhoto photo,
  }) async {
    final fields = <String, String>{
      'submission_uuid': submissionUuid,
      'oil_quantity': quantity.toString(),
      'oil_unit': unit,
      'notes': notes.trim(),
      if (greaseTrapId != null) 'grease_trap_id': '$greaseTrapId',
    };
    try {
      final data = await auth.postMultipart(
        'oil-surrender.php',
        fields: fields,
        fileField: 'photo',
        filePath: photo.file.path,
        filename: photo.filename,
        fileBytes: kIsWeb ? await photo.file.readAsBytes() : null,
      );
      return SurrenderSubmissionResult(
        surrender: OilSurrender.fromJson(
          data['surrender'] as Map<String, dynamic>,
        ),
        idempotentReplay: data['idempotent_replay'] == true,
      );
    } on FormatException {
      throw const ApiException(
        'The server returned unreadable submission information.',
        type: ApiErrorType.serverError,
      );
    }
  }

  static String newSubmissionUuid([Random? random]) {
    final generator = random ?? Random.secure();
    final bytes = List<int>.generate(16, (_) => generator.nextInt(256));
    bytes[6] = (bytes[6] & 0x0f) | 0x40;
    bytes[8] = (bytes[8] & 0x3f) | 0x80;
    final hex = bytes
        .map((value) => value.toRadixString(16).padLeft(2, '0'))
        .join();
    return '${hex.substring(0, 8)}-${hex.substring(8, 12)}-'
        '${hex.substring(12, 16)}-${hex.substring(16, 20)}-${hex.substring(20)}';
  }
}
