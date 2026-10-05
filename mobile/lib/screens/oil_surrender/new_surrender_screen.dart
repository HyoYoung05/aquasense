import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:image_picker/image_picker.dart';

import '../../models/oil_surrender.dart';
import '../../services/api_service.dart';
import '../../services/oil_surrender_service.dart';
import '../../utils/formatters.dart';

class NewSurrenderScreen extends StatefulWidget {
  final OilSurrenderRepository repository;
  final List<SurrenderTrapOption> traps;
  final int establishmentCount;
  final EvidencePicker? evidencePicker;

  const NewSurrenderScreen({
    super.key,
    required this.repository,
    required this.traps,
    required this.establishmentCount,
    this.evidencePicker,
  });

  @override
  State<NewSurrenderScreen> createState() => _NewSurrenderScreenState();
}

class _NewSurrenderScreenState extends State<NewSurrenderScreen> {
  final _formKey = GlobalKey<FormState>();
  final _quantityController = TextEditingController();
  final _notesController = TextEditingController();
  late final EvidencePicker _picker;
  late String _submissionUuid;
  String _unit = 'L';
  int? _trapId;
  EvidencePhoto? _photo;
  String? _photoError;
  String? _submitError;
  bool _submitting = false;
  bool _picking = false;

  @override
  void initState() {
    super.initState();
    _picker = widget.evidencePicker ?? ImagePickerEvidence();
    _submissionUuid = OilSurrenderService.newSubmissionUuid();
    if (widget.traps.length == 1) _trapId = widget.traps.single.id;
  }

  @override
  void dispose() {
    _quantityController.dispose();
    _notesController.dispose();
    super.dispose();
  }

  String? _validateQuantity(String? text) {
    final value = double.tryParse(text?.trim() ?? '');
    if (text == null || text.trim().isEmpty) return 'Enter the oil quantity.';
    if (value == null) return 'Enter a valid number.';
    if (!value.isFinite || value <= 0) {
      return 'Quantity must be greater than zero.';
    }
    if (value > 9999999.999) return 'Quantity is above the supported limit.';
    return null;
  }

  Future<void> _choosePhoto(ImageSource source) async {
    if (_picking || _submitting) return;
    setState(() {
      _picking = true;
      _photoError = null;
    });
    try {
      final file = await _picker.pick(source);
      if (file == null) return;
      final evidence = await EvidencePhoto.validate(file);
      if (mounted) setState(() => _photo = evidence);
    } on ApiException catch (exception) {
      if (mounted) setState(() => _photoError = exception.message);
    } on PlatformException {
      if (mounted) {
        setState(
          () => _photoError =
              'Photo access was unavailable. Check the app permission and try again.',
        );
      }
    } catch (_) {
      if (mounted) {
        setState(() => _photoError = 'Could not open the selected photo.');
      }
    } finally {
      if (mounted) setState(() => _picking = false);
    }
  }

  Future<void> _photoSource() async {
    final source = await showModalBottomSheet<ImageSource>(
      context: context,
      showDragHandle: true,
      builder: (context) => SafeArea(
        child: Wrap(
          children: [
            ListTile(
              leading: const Icon(Icons.camera_alt_outlined),
              title: const Text('Take a photo'),
              onTap: () => Navigator.pop(context, ImageSource.camera),
            ),
            ListTile(
              leading: const Icon(Icons.photo_library_outlined),
              title: const Text('Choose from gallery'),
              onTap: () => Navigator.pop(context, ImageSource.gallery),
            ),
          ],
        ),
      ),
    );
    if (source != null && mounted) await _choosePhoto(source);
  }

  Future<void> _submit() async {
    if (_submitting) return;
    final formValid = _formKey.currentState?.validate() ?? false;
    final trapRequired = widget.traps.length > 1;
    setState(() {
      _photoError = _photo == null ? 'Photo evidence is required.' : null;
      _submitError = null;
    });
    if (!formValid || _photo == null || (trapRequired && _trapId == null)) {
      return;
    }
    if (widget.establishmentCount < 1) {
      setState(
        () => _submitError =
            'No active establishment is linked to this Owner account.',
      );
      return;
    }

    final quantity = double.parse(_quantityController.text.trim());
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Confirm oil surrender'),
        content: Text(
          'Submit ${cleanNumber(quantity, decimals: 3)} $_unit with '
          '${_photo!.filename} for Barangay review?\n\n'
          'The submitted record cannot be edited in the Owner app.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Cancel'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Submit'),
          ),
        ],
      ),
    );
    if (confirmed != true || !mounted) return;

    setState(() => _submitting = true);
    try {
      final result = await widget.repository.submit(
        submissionUuid: _submissionUuid,
        quantity: quantity,
        unit: _unit,
        greaseTrapId: _trapId,
        notes: _notesController.text,
        photo: _photo!,
      );
      if (!mounted) return;
      await showDialog<void>(
        context: context,
        barrierDismissible: false,
        builder: (context) => AlertDialog(
          icon: const Icon(
            Icons.check_circle_outline,
            color: Colors.green,
            size: 44,
          ),
          title: Text(
            result.idempotentReplay
                ? 'Submission already received'
                : 'Oil surrender submitted',
          ),
          content: Text(
            'Transaction: ${result.surrender.transactionCode}\n'
            'Status: ${surrenderStatusLabel(result.surrender.status)}',
          ),
          actions: [
            FilledButton(
              onPressed: () => Navigator.pop(context),
              child: const Text('Done'),
            ),
          ],
        ),
      );
      if (mounted) Navigator.pop(context, result.surrender);
    } on ApiException catch (exception) {
      if (mounted && exception.type != ApiErrorType.unauthorized) {
        setState(() => _submitError = exception.message);
      }
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('New Oil Surrender')),
    body: Form(
      key: _formKey,
      child: ListView(
        padding: const EdgeInsets.fromLTRB(16, 8, 16, 32),
        children: [
          Center(
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 680),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Card(
                    child: ListTile(
                      leading: Icon(Icons.info_outline),
                      title: Text('Evidence for Barangay review'),
                      subtitle: Text(
                        'Provide the actual surrendered quantity and one clear photo. The Barangay makes the final review decision.',
                      ),
                    ),
                  ),
                  const SizedBox(height: 16),
                  TextFormField(
                    key: const Key('surrender_quantity'),
                    controller: _quantityController,
                    enabled: !_submitting,
                    keyboardType: const TextInputType.numberWithOptions(
                      decimal: true,
                    ),
                    inputFormatters: [
                      FilteringTextInputFormatter.allow(RegExp(r'[0-9.]')),
                    ],
                    decoration: const InputDecoration(
                      labelText: 'Oil quantity',
                      prefixIcon: Icon(Icons.oil_barrel_outlined),
                    ),
                    validator: _validateQuantity,
                  ),
                  const SizedBox(height: 14),
                  DropdownButtonFormField<String>(
                    key: const Key('surrender_unit'),
                    initialValue: _unit,
                    decoration: const InputDecoration(
                      labelText: 'Quantity unit',
                      prefixIcon: Icon(Icons.straighten),
                    ),
                    items: const [
                      DropdownMenuItem(value: 'L', child: Text('Liters (L)')),
                      DropdownMenuItem(
                        value: 'kg',
                        child: Text('Kilograms (kg)'),
                      ),
                    ],
                    onChanged: _submitting
                        ? null
                        : (value) => setState(() => _unit = value ?? 'L'),
                  ),
                  if (widget.traps.isNotEmpty) ...[
                    const SizedBox(height: 14),
                    DropdownButtonFormField<int>(
                      key: const Key('surrender_trap'),
                      initialValue: _trapId,
                      decoration: const InputDecoration(
                        labelText: 'Grease trap',
                        prefixIcon: Icon(Icons.water_drop_outlined),
                      ),
                      items: [
                        for (final trap in widget.traps)
                          DropdownMenuItem(
                            value: trap.id,
                            child: Text(
                              '${trap.name} · ${trap.businessName}',
                              overflow: TextOverflow.ellipsis,
                            ),
                          ),
                      ],
                      onChanged: _submitting
                          ? null
                          : (value) => setState(() => _trapId = value),
                      validator: (_) =>
                          widget.traps.length > 1 && _trapId == null
                          ? 'Select the grease trap for this surrender.'
                          : null,
                    ),
                  ],
                  const SizedBox(height: 14),
                  TextFormField(
                    key: const Key('surrender_notes'),
                    controller: _notesController,
                    enabled: !_submitting,
                    minLines: 3,
                    maxLines: 5,
                    maxLength: 2000,
                    decoration: const InputDecoration(
                      labelText: 'Owner notes (optional)',
                      alignLabelWithHint: true,
                    ),
                  ),
                  const SizedBox(height: 8),
                  Text(
                    'Photo evidence',
                    style: Theme.of(context).textTheme.titleMedium?.copyWith(
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  const SizedBox(height: 8),
                  if (_photo == null)
                    OutlinedButton.icon(
                      key: const Key('choose_photo'),
                      onPressed: _picking || _submitting ? null : _photoSource,
                      icon: _picking
                          ? const SizedBox.square(
                              dimension: 20,
                              child: CircularProgressIndicator(strokeWidth: 2),
                            )
                          : const Icon(Icons.add_a_photo_outlined),
                      label: Text(
                        _picking ? 'Opening photos…' : 'Add Photo Evidence',
                      ),
                    )
                  else
                    _PhotoPreview(
                      photo: _photo!,
                      onChange: _submitting ? null : _photoSource,
                      onRemove: _submitting
                          ? null
                          : () => setState(() => _photo = null),
                    ),
                  if (_photoError != null) ...[
                    const SizedBox(height: 8),
                    Text(
                      _photoError!,
                      style: TextStyle(
                        color: Theme.of(context).colorScheme.error,
                      ),
                    ),
                  ],
                  const SizedBox(height: 12),
                  const Text(
                    'Accepted: JPEG, PNG, or WEBP. Maximum: 5 MiB. Camera photos are resized to at most 2048×2048 at readable quality before upload.',
                  ),
                  if (_submitError != null) ...[
                    const SizedBox(height: 16),
                    Card(
                      color: Theme.of(context).colorScheme.errorContainer,
                      child: Padding(
                        padding: const EdgeInsets.all(14),
                        child: Text(_submitError!),
                      ),
                    ),
                  ],
                  const SizedBox(height: 20),
                  FilledButton.icon(
                    key: const Key('submit_surrender'),
                    onPressed: _submitting ? null : _submit,
                    icon: _submitting
                        ? const SizedBox.square(
                            dimension: 20,
                            child: CircularProgressIndicator(strokeWidth: 2),
                          )
                        : const Icon(Icons.cloud_upload_outlined),
                    label: Text(
                      _submitting ? 'Submitting…' : 'Submit for Review',
                    ),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    ),
  );
}

class _PhotoPreview extends StatelessWidget {
  final EvidencePhoto photo;
  final VoidCallback? onChange;
  final VoidCallback? onRemove;

  const _PhotoPreview({
    required this.photo,
    required this.onChange,
    required this.onRemove,
  });

  @override
  Widget build(BuildContext context) => Card(
    clipBehavior: Clip.antiAlias,
    child: Column(
      children: [
        FutureBuilder<Uint8List>(
          future: photo.previewBytes(),
          builder: (context, snapshot) {
            if (snapshot.hasData) {
              return Image.memory(
                snapshot.data!,
                key: const Key('photo_preview'),
                height: 260,
                width: double.infinity,
                fit: BoxFit.contain,
                errorBuilder: (_, _, _) => const SizedBox(
                  height: 180,
                  child: Center(child: Text('Preview unavailable')),
                ),
              );
            }
            return const SizedBox(
              height: 180,
              child: Center(child: CircularProgressIndicator()),
            );
          },
        ),
        Padding(
          padding: const EdgeInsets.all(12),
          child: Row(
            children: [
              Expanded(
                child: Text(
                  photo.filename,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
              ),
              TextButton.icon(
                onPressed: onChange,
                icon: const Icon(Icons.swap_horiz),
                label: const Text('Change'),
              ),
              IconButton(
                onPressed: onRemove,
                tooltip: 'Remove photo',
                icon: const Icon(Icons.delete_outline),
              ),
            ],
          ),
        ),
      ],
    ),
  );
}
