import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';

import '../../core/api/api_exception.dart';
import '../../core/api/platform_repository.dart';
import '../../core/theme/app_theme.dart';
import '../../core/widgets/common.dart';
import 'attachments.dart';
import 'models.dart';
import 'run_status_screen.dart';

/// يقابل resources/views/app/runs/step.blade.php وreview.blade.php
///
/// نفس القواعد: خطوة واحدة في الشاشة، حفظ تلقائي بعد كل خطوة، ومنع التشغيل
/// قبل اكتمال الحقول المطلوبة.
class RunWizardScreen extends StatefulWidget {
  const RunWizardScreen({super.key, required this.repository, required this.run});

  final PlatformRepository repository;
  final ToolRunModel run;

  @override
  State<RunWizardScreen> createState() => _RunWizardScreenState();
}

class _RunWizardScreenState extends State<RunWizardScreen> {
  late ToolRunModel _run = widget.run;
  late int _stepIndex = (widget.run.currentStep - 1).clamp(0, _lastIndex);

  final Map<String, dynamic> _draft = {};
  bool _busy = false;
  bool _reviewing = false;
  String? _error;
  Preflight? _preflight;
  List<RunFile> _files = const [];
  bool _uploading = false;

  Future<void> _pickAndUpload() async {
    final picked = await FilePicker.platform.pickFiles(withData: false);
    final path = picked?.files.single.path;

    if (path == null) return;

    setState(() => _uploading = true);

    try {
      _files = await widget.repository.uploadFile(_run.uuid, path);
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(error.toString())));
      }
    } finally {
      if (mounted) setState(() => _uploading = false);
    }
  }

  Future<void> _removeFile(int id) async {
    setState(() => _uploading = true);

    try {
      _files = await widget.repository.deleteFile(_run.uuid, id);
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(error.toString())));
      }
    } finally {
      if (mounted) setState(() => _uploading = false);
    }
  }

  int get _lastIndex => (widget.run.steps.length - 1).clamp(0, 999);

  WizardStep get _step => _run.steps[_stepIndex];

  @override
  void initState() {
    super.initState();
    _seedDraft();
  }

  void _seedDraft() {
    _draft.clear();

    for (final field in _step.fields) {
      _draft[field.key] = field.type == 'multiselect' ? field.selectedValues : field.value;
    }
  }

  Future<void> _saveAndAdvance() async {
    final missing = _step.fields
        .where((field) => field.required && _isEmpty(_draft[field.key]))
        .map((field) => field.label)
        .toList();

    if (missing.isNotEmpty) {
      setState(() => _error = 'أكمل: ${missing.join('، ')}');
      return;
    }

    setState(() {
      _busy = true;
      _error = null;
    });

    try {
      final updated = await widget.repository.saveStep(_run.uuid, _step.step, _draft);
      _run = updated;

      if (_stepIndex >= _run.steps.length - 1) {
        final preflight = await widget.repository.preflight(_run.uuid);
        setState(() {
          _preflight = preflight;
          _reviewing = true;
        });
      } else {
        setState(() {
          _stepIndex++;
          _seedDraft();
        });
      }
    } on ApiException catch (exception) {
      setState(() => _error = exception.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _queue() async {
    setState(() {
      _busy = true;
      _error = null;
    });

    try {
      final queued = await widget.repository.queueRun(_run.uuid);

      if (!mounted) return;

      Navigator.of(context).pushReplacement(
        MaterialPageRoute(
          builder: (_) => RunStatusScreen(repository: widget.repository, run: queued),
        ),
      );
    } on ApiException catch (exception) {
      setState(() {
        _error = exception.message;
        _busy = false;
      });
    }
  }

  bool _isEmpty(dynamic value) {
    if (value is List) return value.isEmpty;
    return value == null || value.toString().trim().isEmpty;
  }

  @override
  Widget build(BuildContext context) {
    if (_reviewing) return _buildReview();

    final progress = (_stepIndex + 1) / _run.steps.length;

    return Scaffold(
      appBar: AppBar(title: Text(_run.toolTitle)),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Text('${_run.projectName} · ${_step.title}',
              style: const TextStyle(color: BrandColors.muted)),
          const SizedBox(height: 12),

          ClipRRect(
            borderRadius: BorderRadius.circular(999),
            child: LinearProgressIndicator(value: progress, minHeight: 8),
          ),
          const SizedBox(height: 6),
          Text('الخطوة ${_stepIndex + 1} من ${_run.steps.length}',
              style: const TextStyle(color: BrandColors.muted, fontSize: 13)),
          const SizedBox(height: 20),

          if (_error != null) ...[ErrorNotice(message: _error!), const SizedBox(height: 16)],

          for (final field in _step.fields) ...[
            _buildField(field),
            const SizedBox(height: 18),
          ],

          const SizedBox(height: 8),
          Row(
            children: [
              if (_stepIndex > 0)
                Expanded(
                  child: OutlinedButton(
                    onPressed: _busy
                        ? null
                        : () => setState(() {
                              _stepIndex--;
                              _seedDraft();
                            }),
                    child: const Text('السابق'),
                  ),
                ),
              if (_stepIndex > 0) const SizedBox(width: 12),
              Expanded(
                child: FilledButton(
                  onPressed: _busy ? null : _saveAndAdvance,
                  child: _busy
                      ? const SizedBox(
                          height: 20,
                          width: 20,
                          child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                        )
                      : Text(_stepIndex >= _run.steps.length - 1 ? 'إلى المراجعة' : 'التالي'),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildField(ToolFieldModel field) {
    final label = field.required ? field.label : '${field.label} (اختياري)';

    return switch (field.type) {
      'textarea' => TextFormField(
          initialValue: _draft[field.key]?.toString(),
          decoration: InputDecoration(labelText: label, helperText: field.help),
          maxLines: 4,
          onChanged: (value) => _draft[field.key] = value,
        ),
      'number' => TextFormField(
          initialValue: _draft[field.key]?.toString(),
          decoration: InputDecoration(labelText: label, helperText: field.help),
          keyboardType: TextInputType.number,
          onChanged: (value) => _draft[field.key] = value,
        ),
      'select' => DropdownButtonFormField<String>(
          initialValue: field.options.any((o) => o.value == _draft[field.key]?.toString())
              ? _draft[field.key].toString()
              : null,
          decoration: InputDecoration(labelText: label, helperText: field.help),
          items: field.options
              .map((option) =>
                  DropdownMenuItem(value: option.value, child: Text(option.label)))
              .toList(),
          onChanged: (value) => setState(() => _draft[field.key] = value),
        ),
      'multiselect' => _buildMultiSelect(field, label),
      _ => TextFormField(
          initialValue: _draft[field.key]?.toString(),
          decoration: InputDecoration(labelText: label, helperText: field.help),
          onChanged: (value) => _draft[field.key] = value,
        ),
    };
  }

  Widget _buildMultiSelect(ToolFieldModel field, String label) {
    final selected = List<String>.from(_draft[field.key] as List? ?? const []);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(label, style: const TextStyle(fontWeight: FontWeight.w600, color: BrandColors.navy)),
        if (field.help != null)
          Padding(
            padding: const EdgeInsets.only(top: 2),
            child: Text(field.help!,
                style: const TextStyle(color: BrandColors.muted, fontSize: 12)),
          ),
        const SizedBox(height: 8),
        Wrap(
          spacing: 8,
          runSpacing: 8,
          children: field.options.map((option) {
            final isOn = selected.contains(option.value);

            return FilterChip(
              label: Text(option.label),
              selected: isOn,
              onSelected: (value) => setState(() {
                value ? selected.add(option.value) : selected.remove(option.value);
                _draft[field.key] = selected;
              }),
            );
          }).toList(),
        ),
      ],
    );
  }

  Widget _buildReview() {
    final preflight = _preflight;

    return Scaffold(
      appBar: AppBar(title: const Text('مراجعة قبل التشغيل')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Text('${_run.toolTitle} · ${_run.projectName}',
              style: const TextStyle(color: BrandColors.muted)),
          const SizedBox(height: 6),
          const Text('راجع قبل التحليل',
              style: TextStyle(fontSize: 20, fontWeight: FontWeight.w700)),
          const SizedBox(height: 4),
          const Text('هذه آخر فرصة لتصحيح مدخل قبل أن يُبنى عليه التقرير.',
              style: TextStyle(color: BrandColors.muted)),
          const SizedBox(height: 18),

          if (_error != null) ...[ErrorNotice(message: _error!), const SizedBox(height: 16)],

          BrandCard(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Eyebrow('اكتمال البيانات'),
                const SizedBox(height: 6),
                Text('${preflight?.percent ?? 0}%',
                    style: const TextStyle(fontSize: 32, fontWeight: FontWeight.w700)),
                const SizedBox(height: 8),
                if (preflight != null && preflight.missing.isEmpty)
                  const Text('كل الحقول المطلوبة مكتملة.',
                      style: TextStyle(color: BrandColors.muted))
                else
                  ...[
                    const Text('أكمل أولًا:',
                        style: TextStyle(color: BrandColors.red, fontWeight: FontWeight.w600)),
                    for (final item in preflight?.missing ?? const <String>[])
                      Text('• $item'),
                  ],
              ],
            ),
          ),
          const SizedBox(height: 12),

          BrandCard(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Eyebrow('ما سيُعامل كافتراض'),
                const SizedBox(height: 6),
                if (preflight == null || preflight.assumptions.isEmpty)
                  const Text('لا شيء. كل ما أدخلته سيُعامل كبيانات موثقة منك.',
                      style: TextStyle(color: BrandColors.muted))
                else
                  for (final item in preflight.assumptions) Text('• $item'),
              ],
            ),
          ),
          const SizedBox(height: 12),

          // رفع الأدلة — نظير قسم المرفقات في مراجعة الويب.
          BrandCard(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Eyebrow('أرفق أدلة (اختياري)'),
                const SizedBox(height: 6),
                const Text('PDF أو Word أو Excel أو صورة أو نص. نقرأها ونضيفها للتحليل.',
                    style: TextStyle(color: BrandColors.muted, fontSize: 13)),
                const SizedBox(height: 10),
                for (final file in _files) ...[
                  Row(
                    children: [
                      const Icon(Icons.insert_drive_file_outlined, size: 18, color: BrandColors.muted),
                      const SizedBox(width: 6),
                      Expanded(child: Text(file.name, overflow: TextOverflow.ellipsis)),
                      Text(file.statusLabel,
                          style: const TextStyle(color: BrandColors.muted, fontSize: 12)),
                      IconButton(
                        icon: const Icon(Icons.close, size: 18),
                        onPressed: _uploading ? null : () => _removeFile(file.id),
                      ),
                    ],
                  ),
                ],
                OutlinedButton.icon(
                  onPressed: _uploading ? null : _pickAndUpload,
                  icon: _uploading
                      ? const SizedBox(height: 16, width: 16, child: CircularProgressIndicator(strokeWidth: 2))
                      : const Icon(Icons.upload_file),
                  label: const Text('أرفق ملفًا'),
                ),
              ],
            ),
          ),
          const SizedBox(height: 20),

          FilledButton(
            onPressed: (_busy || preflight?.isReady != true) ? null : _queue,
            child: _busy
                ? const SizedBox(
                    height: 20,
                    width: 20,
                    child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                  )
                : const Text('ابدأ التحليل'),
          ),
          const SizedBox(height: 10),
          OutlinedButton(
            onPressed: _busy
                ? null
                : () => setState(() {
                      _reviewing = false;
                      _seedDraft();
                    }),
            child: const Text('عودة للتعديل'),
          ),
        ],
      ),
    );
  }
}
