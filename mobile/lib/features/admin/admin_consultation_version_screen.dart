import 'package:flutter/material.dart';

import '../../core/api/platform_repository.dart';
import '../../core/theme/app_theme.dart';
import '../../core/widgets/adaptive_layout.dart';
import '../../core/widgets/common.dart';

/// تفصيل إصدار مخطط استشارة: وحداته وأسئلته، وتحرير السؤال ونشر المسودة —
/// نظير `views/admin/consultations/show.blade.php`.
class AdminConsultationVersionScreen extends StatefulWidget {
  const AdminConsultationVersionScreen({
    super.key,
    required this.repository,
    required this.versionId,
  });

  final PlatformRepository repository;
  final int versionId;

  @override
  State<AdminConsultationVersionScreen> createState() =>
      _AdminConsultationVersionScreenState();
}

class _AdminConsultationVersionScreenState
    extends State<AdminConsultationVersionScreen> {
  late Future<Map<String, dynamic>> _future;
  bool _busy = false;

  @override
  void initState() {
    super.initState();
    _load();
  }

  void _load() {
    _future = widget.repository.adminConsultationVersion(widget.versionId);
  }

  Future<void> _publish() async {
    setState(() => _busy = true);
    try {
      await widget.repository.adminPublishConsultation(widget.versionId);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('نُشر الإصدار وقُفل ضد التعديل.')),
      );
      setState(_load);
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(userErrorMessage(error))));
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _editQuestion(Map<String, dynamic> question) async {
    final userText = TextEditingController(
      text: question['user_text']?.toString() ?? '',
    );
    final helpText = TextEditingController(
      text: question['help_text']?.toString() ?? '',
    );
    final whyText = TextEditingController(
      text: question['why_text']?.toString() ?? '',
    );
    var required = question['required'] == true;
    var allowUnknown = question['allow_unknown'] == true;
    var allowSkip = question['allow_skip'] == true;

    final saved = await showDialog<bool>(
      context: context,
      builder: (context) => StatefulBuilder(
        builder: (context, setDialogState) => AlertDialog(
          title: Text(question['key']?.toString() ?? 'تحرير السؤال'),
          content: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                TextField(
                  controller: userText,
                  minLines: 2,
                  maxLines: 4,
                  decoration: const InputDecoration(labelText: 'نص السؤال'),
                ),
                TextField(
                  controller: helpText,
                  maxLines: 2,
                  decoration: const InputDecoration(labelText: 'المساعدة'),
                ),
                TextField(
                  controller: whyText,
                  maxLines: 2,
                  decoration: const InputDecoration(labelText: 'لماذا نسأل؟'),
                ),
                CheckboxListTile(
                  contentPadding: EdgeInsets.zero,
                  value: required,
                  title: const Text('إلزامي'),
                  onChanged: (v) => setDialogState(() => required = v ?? false),
                ),
                CheckboxListTile(
                  contentPadding: EdgeInsets.zero,
                  value: allowUnknown,
                  title: const Text('يسمح بـ«لا أعرف»'),
                  onChanged: (v) =>
                      setDialogState(() => allowUnknown = v ?? false),
                ),
                CheckboxListTile(
                  contentPadding: EdgeInsets.zero,
                  value: allowSkip,
                  title: const Text('يسمح بالتخطي'),
                  onChanged: (v) =>
                      setDialogState(() => allowSkip = v ?? false),
                ),
              ],
            ),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.of(context).pop(false),
              child: const Text('إلغاء'),
            ),
            FilledButton(
              onPressed: userText.text.trim().length >= 3
                  ? () => Navigator.of(context).pop(true)
                  : null,
              child: const Text('حفظ'),
            ),
          ],
        ),
      ),
    );

    if (saved != true) return;

    setState(() => _busy = true);
    try {
      await widget.repository.adminUpdateConsultationQuestion(
        widget.versionId,
        question['id'] as int,
        {
          'user_text': userText.text.trim(),
          'help_text': helpText.text.trim(),
          'why_text': whyText.text.trim(),
          'required': required,
          'allow_unknown': allowUnknown,
          'allow_skip': allowSkip,
        },
      );
      if (!mounted) return;
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(const SnackBar(content: Text('حُفظ السؤال في المسودة.')));
      setState(_load);
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(userErrorMessage(error))));
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('تحرير الإصدار')),
      body: FutureBuilder<Map<String, dynamic>>(
        future: _future,
        builder: (context, snapshot) => AsyncView(
          snapshot: snapshot,
          onRetry: () => setState(_load),
          builder: _body,
        ),
      ),
    );
  }

  Widget _body(Map<String, dynamic> version) {
    final editable = version['is_editable'] == true;
    final modules = (version['modules'] as List? ?? const [])
        .cast<Map<String, dynamic>>();

    return AdaptivePage(
      family: AdaptivePageFamily.operational,
      child: ListView(
        padding: EdgeInsets.zero,
        children: [
          Eyebrow(
            '${version['blueprint']?['name'] ?? ''} · الإصدار ${version['version']}',
          ),
          const SizedBox(height: 4),
          Text(
            editable ? 'مسودة قابلة للتحرير' : 'إصدار منشور مقفل',
            style: TextStyle(
              color: editable ? BrandColors.orange : BrandColors.success,
            ),
          ),
          const SizedBox(height: 12),
          if (editable)
            FilledButton.icon(
              onPressed: _busy ? null : _publish,
              icon: const Icon(Icons.publish_outlined),
              label: const Text('انشر الإصدار'),
            ),
          const SizedBox(height: 12),
          for (final module in modules) _moduleCard(module, editable),
        ],
      ),
    );
  }

  Widget _moduleCard(Map<String, dynamic> module, bool editable) {
    final questions = (module['questions'] as List? ?? const [])
        .cast<Map<String, dynamic>>();

    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: BrandCard(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              module['name']?.toString() ?? '',
              style: const TextStyle(
                fontWeight: FontWeight.w700,
                color: BrandColors.navy,
              ),
            ),
            const Divider(),
            for (final question in questions)
              ListTile(
                contentPadding: EdgeInsets.zero,
                title: Text(question['user_text']?.toString() ?? ''),
                subtitle: Text(
                  '${question['key']} · ${question['answer_type']}'
                  '${question['required'] == true ? ' · إلزامي' : ''}',
                  style: const TextStyle(fontSize: 11),
                ),
                trailing: editable
                    ? const Icon(Icons.edit_outlined, size: 18)
                    : null,
                onTap: editable ? () => _editQuestion(question) : null,
              ),
          ],
        ),
      ),
    );
  }
}
