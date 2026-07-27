import 'dart:async';

import 'package:flutter/material.dart';
import 'package:file_picker/file_picker.dart';

import '../../core/api/platform_repository.dart';
import '../../core/theme/app_theme.dart';
import '../../core/widgets/common.dart';
import '../agency_reports/agency_report_screen.dart';
import 'models.dart';

class ConsultationScreen extends StatefulWidget {
  const ConsultationScreen({
    super.key,
    required this.repository,
    required this.projectSlug,
  });
  final PlatformRepository repository;
  final String projectSlug;

  @override
  State<ConsultationScreen> createState() => _ConsultationScreenState();
}

class _ConsultationScreenState extends State<ConsultationScreen> {
  ConsultationSessionModel? _session;
  String? _selected;
  final Set<String> _selectedMany = {};
  final TextEditingController _text = TextEditingController();
  final TextEditingController _secondaryText = TextEditingController();
  final Map<String, int> _ranking = {};
  double _scale = 1;
  Timer? _poller;
  bool _busy = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _start();
  }

  @override
  void dispose() {
    _poller?.cancel();
    _text.dispose();
    _secondaryText.dispose();
    super.dispose();
  }

  Future<void> _start() async =>
      _run(() => widget.repository.startConsultation(widget.projectSlug));

  Future<void> _run(Future<ConsultationSessionModel> Function() action) async {
    if (mounted) {
      setState(() {
        _busy = true;
        _error = null;
      });
    }
    try {
      final value = await action();
      if (!mounted) return;
      setState(() {
        _session = value;
        _busy = false;
        _selected = null;
        _selectedMany.clear();
        _text.clear();
        _secondaryText.clear();
        _ranking.clear();
        _scale = (value.question?.validation['min'] as num? ?? 1).toDouble();
      });
      _configurePolling(value);
    } catch (error) {
      if (mounted) {
        setState(() {
          _error = error.toString();
          _busy = false;
        });
      }
    }
  }

  void _configurePolling(ConsultationSessionModel session) {
    _poller?.cancel();
    if (!session.isQueued) return;
    _poller = Timer.periodic(const Duration(seconds: 6), (_) async {
      try {
        final next = await widget.repository.consultationStatus(session.uuid);
        if (!mounted) return;
        setState(() => _session = next);
        if (!next.isQueued) _configurePolling(next);
      } catch (_) {
        // تبقى الشاشة قابلة للسحب والتحديث؛ فشل دورة مراقبة لا يمحو الحالة.
      }
    });
  }

  dynamic _answerValue(ConsultationQuestion question) {
    if (question.isMultipleChoice) return _selectedMany.toList();
    if (question.isSingleChoice) return _selected;
    if (question.isNumber) return num.tryParse(_text.text.trim());
    if (question.type == 'scale') return _scale;
    if (question.type == 'range') {
      return {
        'min': num.tryParse(_text.text.trim()),
        'max': num.tryParse(_secondaryText.text.trim()),
      };
    }
    if (question.type == 'ranking') return Map<String, int>.from(_ranking);
    if (question.type == 'repeater') {
      return _text.text
          .split('\n')
          .map((item) => item.trim())
          .where((item) => item.isNotEmpty)
          .toList();
    }
    return _text.text.trim();
  }

  Future<void> _answer({bool unknown = false, bool skipped = false}) async {
    final session = _session;
    final question = session?.question;
    if (session == null || question == null) return;
    final value = _answerValue(question);
    final empty =
        value == null || value == '' || (value is List && value.isEmpty);
    if (!unknown && !skipped && empty) {
      setState(() => _error = 'أدخل إجابة أو اختر «لا أعرف».');
      return;
    }
    FocusManager.instance.primaryFocus?.unfocus();
    await _run(
      () => widget.repository.answerConsultation(
        session.uuid,
        question.key,
        value: value,
        unknown: unknown,
        skipped: skipped,
      ),
    );
  }

  Future<void> _resolve(ConsultationConflict conflict) async {
    final controller = TextEditingController();
    final resolution = await showDialog<String>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('وضّح المعلومة الصحيحة'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(conflict.message),
            const SizedBox(height: 12),
            TextField(
              controller: controller,
              minLines: 2,
              maxLines: 5,
              autofocus: true,
            ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('إلغاء'),
          ),
          FilledButton(
            onPressed: () {
              if (controller.text.trim().length >= 5) {
                Navigator.pop(context, controller.text.trim());
              }
            },
            child: const Text('حفظ'),
          ),
        ],
      ),
    );
    controller.dispose();
    if (resolution == null || _session == null) return;
    await _run(
      () => widget.repository.resolveConsultationConflict(
        _session!.uuid,
        conflict.id,
        resolution,
      ),
    );
  }

  Future<void> _editReview(ConsultationReviewItem item) async {
    final session = _session;
    if (session == null || item.questionKey == null) return;
    dynamic value;
    if (item.type == 'multiselect' && item.options.isNotEmpty) {
      final selected = <String>{
        ...item.value is List
            ? (item.value as List).map((value) => value.toString())
            : const <String>[],
      };
      value = await showDialog<List<String>>(
        context: context,
        builder: (context) => StatefulBuilder(
          builder: (context, setDialogState) => AlertDialog(
            title: Text(item.label),
            content: SingleChildScrollView(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  for (final option in item.options)
                    CheckboxListTile(
                      value: selected.contains(option.value),
                      title: Text(option.label),
                      onChanged: (checked) => setDialogState(() {
                        if (checked == true) {
                          selected.add(option.value);
                        } else {
                          selected.remove(option.value);
                        }
                      }),
                    ),
                ],
              ),
            ),
            actions: [
              TextButton(
                onPressed: () => Navigator.pop(context),
                child: const Text('إلغاء'),
              ),
              FilledButton(
                onPressed: selected.isEmpty
                    ? null
                    : () => Navigator.pop(context, selected.toList()),
                child: const Text('حفظ'),
              ),
            ],
          ),
        ),
      );
    } else if (item.type == 'ranking' && item.options.isNotEmpty) {
      final ranking = <String, int>{};
      if (item.value is Map) {
        for (final entry in (item.value as Map).entries) {
          ranking[entry.key.toString()] = (entry.value as num).toInt();
        }
      }
      value = await showDialog<Map<String, int>>(
        context: context,
        builder: (context) => StatefulBuilder(
          builder: (context, setDialogState) => AlertDialog(
            title: Text(item.label),
            content: SingleChildScrollView(
              child: Column(
                children: [
                  for (final option in item.options)
                    DropdownButtonFormField<int>(
                      initialValue: ranking[option.value],
                      decoration: InputDecoration(labelText: option.label),
                      items: [
                        for (var rank = 1; rank <= item.options.length; rank++)
                          DropdownMenuItem(value: rank, child: Text('$rank')),
                      ],
                      onChanged: (rank) => setDialogState(() {
                        if (rank != null) ranking[option.value] = rank;
                      }),
                    ),
                ],
              ),
            ),
            actions: [
              TextButton(
                onPressed: () => Navigator.pop(context),
                child: const Text('إلغاء'),
              ),
              FilledButton(
                onPressed: ranking.length == item.options.length
                    ? () => Navigator.pop(context, ranking)
                    : null,
                child: const Text('حفظ'),
              ),
            ],
          ),
        ),
      );
    } else if (item.options.isNotEmpty) {
      value = await showDialog<String>(
        context: context,
        builder: (context) => SimpleDialog(
          title: Text(item.label),
          children: [
            for (final option in item.options)
              SimpleDialogOption(
                onPressed: () => Navigator.pop(context, option.value),
                child: Text(option.label),
              ),
          ],
        ),
      );
    } else {
      final controller = TextEditingController(text: item.displayValue);
      value = await showDialog<String>(
        context: context,
        builder: (context) => AlertDialog(
          title: Text(item.label),
          content: TextField(
            controller: controller,
            keyboardType: item.type == 'number'
                ? TextInputType.number
                : TextInputType.multiline,
            minLines: 1,
            maxLines: item.type == 'number' ? 1 : 5,
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(context),
              child: const Text('إلغاء'),
            ),
            FilledButton(
              onPressed: () => Navigator.pop(context, controller.text.trim()),
              child: const Text('حفظ'),
            ),
          ],
        ),
      );
      controller.dispose();
      if (value is String && (item.type == 'number' || item.type == 'scale')) {
        value = num.tryParse(value.trim());
      } else if (value is String && item.type == 'repeater') {
        value = value
            .split('\n')
            .map((entry) => entry.trim())
            .where((entry) => entry.isNotEmpty)
            .toList();
      } else if (value is String && item.type == 'range') {
        final parts = value.split(RegExp(r'\s*[-،,]\s*'));
        if (parts.length >= 2) {
          value = {
            'min': num.tryParse(parts[0]),
            'max': num.tryParse(parts[1]),
          };
        }
      }
    }
    if (value == null || value == '') return;
    await _run(
      () => widget.repository.answerConsultation(
        session.uuid,
        item.questionKey!,
        value: value,
      ),
    );
  }

  Future<void> _delete() async {
    final session = _session;
    if (session == null) return;
    final approved = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('حذف بيانات الاستشارة؟'),
        content: const Text(
          'سيبقى المشروع وأي تقرير منشور، وتحذف الإجابات والأحداث الخاصة بهذه الاستشارة.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('إلغاء'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, true),
            child: const Text('حذف'),
          ),
        ],
      ),
    );
    if (approved != true) return;
    setState(() => _busy = true);
    try {
      await widget.repository.deleteConsultation(session.uuid);
      if (mounted) {
        Navigator.pop(context, true);
      }
    } catch (error) {
      if (mounted) {
        setState(() {
          _busy = false;
          _error = error.toString();
        });
      }
    }
  }

  Future<void> _uploadEvidence() async {
    final session = _session;
    if (session == null) return;
    final result = await FilePicker.platform.pickFiles(
      type: FileType.custom,
      allowedExtensions: const [
        'pdf',
        'doc',
        'docx',
        'xls',
        'xlsx',
        'csv',
        'txt',
        'png',
        'jpg',
        'jpeg',
        'webp',
      ],
    );
    final path = result?.files.single.path;
    if (path == null) return;
    await _run(
      () => widget.repository.uploadConsultationEvidence(session.uuid, path),
    );
  }

  Future<void> _deleteEvidence(int id) async {
    final session = _session;
    if (session == null) return;
    await _run(
      () => widget.repository.deleteConsultationEvidence(session.uuid, id),
    );
  }

  @override
  Widget build(BuildContext context) {
    if (_busy && _session == null) {
      return const Scaffold(body: Center(child: CircularProgressIndicator()));
    }
    if (_session == null) {
      return Scaffold(
        appBar: AppBar(title: const Text('تشخيص مشروعك')),
        body: Center(
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: ErrorNotice(
              message: _error ?? 'تعذر فتح الاستشارة.',
              onRetry: _start,
            ),
          ),
        ),
      );
    }
    final session = _session!;
    return Scaffold(
      appBar: AppBar(
        title: const Text('تشخيص مشروعك'),
        actions: [
          IconButton(
            onPressed: _busy ? null : _delete,
            tooltip: 'حذف بيانات الاستشارة',
            icon: const Icon(Icons.delete_outline),
          ),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: () =>
            _run(() => widget.repository.consultation(session.uuid)),
        child: ListView(
          padding: const EdgeInsets.all(16),
          keyboardDismissBehavior: ScrollViewKeyboardDismissBehavior.onDrag,
          children: [
            Semantics(
              header: true,
              child: Text(
                session.projectName,
                style: const TextStyle(
                  fontSize: 22,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ),
            const SizedBox(height: 8),
            Semantics(
              label: 'تقدم الاستشارة ${session.progress.percent} بالمئة',
              child: LinearProgressIndicator(
                value: session.progress.percent / 100,
              ),
            ),
            const SizedBox(height: 8),
            Text(session.progress.label),
            const SizedBox(height: 20),
            if (_error != null) ...[
              ErrorNotice(
                message: _error!,
                onRetry: session.isQueued
                    ? () => _run(
                        () =>
                            widget.repository.consultationStatus(session.uuid),
                      )
                    : null,
              ),
              const SizedBox(height: 12),
            ],
            _evidence(session),
            const SizedBox(height: 12),
            if (session.question != null)
              _question(session, session.question!)
            else if (session.isReview)
              _review(session)
            else
              _status(session),
          ],
        ),
      ),
    );
  }

  Widget _question(
    ConsultationSessionModel session,
    ConsultationQuestion question,
  ) => BrandCard(
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        if (question.sensitive)
          const Eyebrow('معلومة حساسة — لا تظهر في التحليلات التشغيلية'),
        Text(
          question.text,
          style: const TextStyle(fontSize: 19, fontWeight: FontWeight.w700),
        ),
        if (question.why != null && question.why!.isNotEmpty)
          Padding(
            padding: const EdgeInsets.only(top: 8),
            child: Text('لماذا نسأل؟ ${question.why}'),
          ),
        if (question.help != null && question.help!.isNotEmpty)
          Padding(
            padding: const EdgeInsets.only(top: 8),
            child: Text(
              question.help!,
              style: const TextStyle(color: BrandColors.muted),
            ),
          ),
        const SizedBox(height: 12),
        if (question.isSingleChoice)
          RadioGroup<String>(
            groupValue: _selected,
            onChanged: _busy
                ? (_) {}
                : (value) => setState(() => _selected = value),
            child: Column(
              children: [
                for (final option in question.options)
                  RadioListTile<String>(
                    contentPadding: EdgeInsets.zero,
                    value: option.value,
                    title: Text(option.label),
                  ),
              ],
            ),
          )
        else if (question.isMultipleChoice)
          Column(
            children: [
              for (final option in question.options)
                CheckboxListTile(
                  contentPadding: EdgeInsets.zero,
                  value: _selectedMany.contains(option.value),
                  title: Text(option.label),
                  onChanged: _busy
                      ? null
                      : (checked) => setState(() {
                          if (checked == true) {
                            _selectedMany.add(option.value);
                          } else {
                            _selectedMany.remove(option.value);
                          }
                        }),
                ),
            ],
          )
        else if (question.type == 'scale')
          Column(
            children: [
              Slider(
                value: _scale.clamp(
                  (question.validation['min'] as num? ?? 1).toDouble(),
                  (question.validation['max'] as num? ?? 10).toDouble(),
                ),
                min: (question.validation['min'] as num? ?? 1).toDouble(),
                max: (question.validation['max'] as num? ?? 10).toDouble(),
                divisions:
                    ((question.validation['max'] as num? ?? 10).toInt() -
                            (question.validation['min'] as num? ?? 1).toInt())
                        .clamp(1, 100),
                label: _scale.round().toString(),
                onChanged: _busy
                    ? null
                    : (value) => setState(() => _scale = value),
              ),
              Text('القيمة: ${_scale.round()}'),
            ],
          )
        else if (question.type == 'range')
          Row(
            children: [
              Expanded(
                child: TextField(
                  controller: _text,
                  keyboardType: TextInputType.number,
                  decoration: const InputDecoration(labelText: 'من'),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: TextField(
                  controller: _secondaryText,
                  keyboardType: TextInputType.number,
                  decoration: const InputDecoration(labelText: 'إلى'),
                ),
              ),
            ],
          )
        else if (question.type == 'ranking')
          Column(
            children: [
              for (final option in question.options)
                DropdownButtonFormField<int>(
                  initialValue: _ranking[option.value],
                  decoration: InputDecoration(
                    labelText: 'ترتيب ${option.label}',
                  ),
                  items: [
                    for (var rank = 1; rank <= question.options.length; rank++)
                      DropdownMenuItem(value: rank, child: Text('$rank')),
                  ],
                  onChanged: _busy
                      ? null
                      : (rank) => setState(() {
                          if (rank != null) _ranking[option.value] = rank;
                        }),
                ),
            ],
          )
        else
          TextField(
            controller: _text,
            keyboardType: question.isNumber
                ? const TextInputType.numberWithOptions(decimal: true)
                : TextInputType.multiline,
            minLines: question.isNumber ? 1 : 3,
            maxLines: question.isNumber ? 1 : 6,
            decoration: InputDecoration(
              labelText: question.type == 'repeater'
                  ? 'كل عنصر في سطر مستقل'
                  : (question.isNumber ? 'القيمة' : 'إجابتك'),
              helperText: question.sensitive ? 'تُستخدم للتشخيص فقط.' : null,
            ),
          ),
        const SizedBox(height: 12),
        SizedBox(
          width: double.infinity,
          height: 48,
          child: FilledButton(
            onPressed: _busy ? null : _answer,
            child: _busy
                ? const SizedBox(
                    width: 20,
                    height: 20,
                    child: CircularProgressIndicator(strokeWidth: 2),
                  )
                : const Text('احفظ وتابع'),
          ),
        ),
        Wrap(
          spacing: 8,
          children: [
            if (question.allowUnknown)
              TextButton(
                onPressed: _busy ? null : () => _answer(unknown: true),
                child: const Text('لا أعرف'),
              ),
            if (question.allowSkip)
              TextButton(
                onPressed: _busy ? null : () => _answer(skipped: true),
                child: const Text('تخطَّ الآن'),
              ),
          ],
        ),
      ],
    ),
  );

  Widget _evidence(ConsultationSessionModel session) => BrandCard(
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Text(
          'ملفات وأدلة',
          style: TextStyle(fontWeight: FontWeight.w700),
        ),
        const SizedBox(height: 4),
        const Text(
          'تُحلل فقط بعد موافقتك في سؤال المصادر.',
          style: TextStyle(color: BrandColors.muted, fontSize: 12),
        ),
        for (final item in session.evidence)
          ListTile(
            contentPadding: EdgeInsets.zero,
            title: Text(item.name),
            subtitle: Text(
              item.reviewRequired ? 'بانتظار مراجعتك' : item.confidence,
            ),
            trailing: IconButton(
              onPressed: _busy ? null : () => _deleteEvidence(item.id),
              tooltip: 'حذف الدليل',
              icon: const Icon(Icons.close),
            ),
          ),
        OutlinedButton.icon(
          onPressed: _busy ? null : _uploadEvidence,
          icon: const Icon(Icons.attach_file),
          label: const Text('ارفع دليلًا'),
        ),
      ],
    ),
  );

  Widget _review(ConsultationSessionModel session) {
    final widgets = <Widget>[
      const Text(
        'راجع ما فهمناه',
        style: TextStyle(fontSize: 20, fontWeight: FontWeight.w700),
      ),
      const SizedBox(height: 8),
      const Text(
        'فرّقنا الحقائق عن التقديرات والافتراضات. حلّ أي تعارض قبل التحليل.',
      ),
      const SizedBox(height: 12),
      _reviewGroup('حقائق صرّحت بها', session.review.facts),
      _reviewGroup('تقديرات تحتاج تحققًا', session.review.estimates),
      _reviewGroup('معلومات غير متاحة', session.review.unknowns),
      _reviewGroup('افتراضات معلنة', session.review.assumptions),
    ];

    if (session.conflicts.isNotEmpty) {
      widgets.add(
        const Text(
          'تعارضات تحتاج توضيحك',
          style: TextStyle(fontSize: 18, fontWeight: FontWeight.w700),
        ),
      );
      widgets.add(const SizedBox(height: 8));
      for (final conflict in session.conflicts) {
        widgets.add(
          Padding(
            padding: const EdgeInsets.only(bottom: 8),
            child: BrandCard(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(conflict.message),
                  const SizedBox(height: 8),
                  OutlinedButton(
                    onPressed: _busy ? null : () => _resolve(conflict),
                    child: const Text('وضّح المعلومة الصحيحة'),
                  ),
                ],
              ),
            ),
          ),
        );
      }
    }

    widgets.add(const SizedBox(height: 12));
    widgets.add(
      SizedBox(
        height: 48,
        child: FilledButton(
          onPressed: !_busy && session.canConfirm
              ? () => _run(
                  () => widget.repository.confirmConsultation(session.uuid),
                )
              : null,
          child: const Text('أكد وابدأ التحليل الشامل'),
        ),
      ),
    );

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: widgets,
    );
  }

  Widget _reviewGroup(String title, List<ConsultationReviewItem> items) =>
      Padding(
        padding: const EdgeInsets.only(bottom: 10),
        child: BrandCard(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(title, style: const TextStyle(fontWeight: FontWeight.w700)),
              const SizedBox(height: 6),
              if (items.isEmpty)
                const Text(
                  'لا توجد عناصر.',
                  style: TextStyle(color: BrandColors.muted),
                )
              else
                for (final item in items)
                  Padding(
                    padding: const EdgeInsets.only(bottom: 5),
                    child: Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Expanded(
                          child: Text(
                            item.displayValue.isEmpty
                                ? '• ${item.label}'
                                : '• ${item.label}: ${item.displayValue}',
                          ),
                        ),
                        if (item.questionKey != null)
                          IconButton(
                            onPressed: _busy ? null : () => _editReview(item),
                            tooltip: 'صحّح الإجابة',
                            icon: const Icon(Icons.edit_outlined, size: 18),
                          ),
                      ],
                    ),
                  ),
            ],
          ),
        ),
      );

  Widget _status(ConsultationSessionModel session) => BrandCard(
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Icon(
          session.isCompleted
              ? Icons.check_circle_outline
              : session.isFailed
              ? Icons.error_outline
              : Icons.hourglass_top,
          size: 44,
          color: session.isFailed ? Colors.red : BrandColors.blue,
        ),
        const SizedBox(height: 10),
        Text(session.statusMessage, textAlign: TextAlign.center),
        const SizedBox(height: 14),
        if (session.isQueued) const Center(child: CircularProgressIndicator()),
        if (session.isFailed)
          FilledButton(
            onPressed: _busy
                ? null
                : () => _run(
                    () => widget.repository.retryConsultation(session.uuid),
                  ),
            child: const Text('أعد محاولة التحليل'),
          ),
        if (session.isCompleted && session.reportUuid != null)
          FilledButton.icon(
            onPressed: () => Navigator.of(context).push(
              MaterialPageRoute(
                builder: (_) => AgencyReportScreen(
                  repository: widget.repository,
                  uuid: session.reportUuid!,
                ),
              ),
            ),
            icon: const Icon(Icons.description_outlined),
            label: const Text('افتح التقرير الموحد'),
          ),
      ],
    ),
  );
}
