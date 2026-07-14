import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../core/error/api_exception.dart';
import '../../data/models/interview_models.dart';
import '../../data/repositories/interview_repository.dart';
import '../../data/services/workspace_service.dart';
import '../shared/widgets/app_state_view.dart';
import '../shared/widgets/ui_feedback.dart';
import '../shared/widgets/voice_input_button.dart';

/// مقابلة المؤسِّس (المرحلة 4): محادثة واحدة تملأ أساس المشروع، فتقترحه الأدوات
/// تلقائياً لاحقاً بدل إعادة كتابته. حتمية — إجابات المستخدم تُحفظ كما هي.
class FounderInterviewPage extends StatefulWidget {
  const FounderInterviewPage({super.key});

  @override
  State<FounderInterviewPage> createState() => _FounderInterviewPageState();
}

class _FounderInterviewPageState extends State<FounderInterviewPage> {
  final _controllers = <String, TextEditingController>{};
  final _loading = true.obs;
  final _saving = false.obs;
  final _error = RxnString();
  final _questions = <InterviewQuestion>[].obs;
  final _voiceEnabled = false.obs;

  late final String _projectId = Get.arguments as String;
  late final InterviewRepository _repo = Get.find<InterviewRepository>();
  late final WorkspaceService _workspaces = Get.find<WorkspaceService>();

  @override
  void dispose() {
    for (final c in _controllers.values) {
      c.dispose();
    }
    super.dispose();
  }

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    final ws = _workspaces.activeId;
    if (ws == null) {
      _loading.value = false;
      _error.value = 'لا توجد مساحة عمل نشطة.';
      return;
    }
    _loading.value = true;
    _error.value = null;
    try {
      final InterviewData data = await _repo.load(ws, _projectId);
      _questions.assignAll(data.questions);
      _voiceEnabled.value = data.voiceEnabled;
      for (final q in data.questions) {
        _controllers.putIfAbsent(q.key, () => TextEditingController());
        _controllers[q.key]!.text = data.answers[q.key] ?? '';
      }
    } on ApiException catch (e) {
      _error.value = e.message;
    } finally {
      _loading.value = false;
    }
  }

  Future<void> _transcribeInto(String key, String filePath) async {
    final ws = _workspaces.activeId;
    if (ws == null) return;
    try {
      final text = await _repo.transcribe(ws, _projectId, filePath);
      if (text.trim().isEmpty) {
        UiFeedback.error('لم نتمكّن من تحويل الصوت إلى نص.', title: 'الصوت');
        return;
      }
      final controller = _controllers[key];
      if (controller != null) {
        final existing = controller.text.trim();
        controller.text = existing.isEmpty ? text : '$existing $text';
      }
    } on ApiException catch (e) {
      UiFeedback.error(e.message, title: 'الصوت');
    }
  }

  Future<void> _save() async {
    final ws = _workspaces.activeId;
    if (ws == null || _saving.value) return;

    final answers = <String, String>{};
    _controllers.forEach((key, c) => answers[key] = c.text.trim());
    if (answers.values.every((v) => v.isEmpty)) {
      UiFeedback.error('اكتب إجابة واحدة على الأقل.', title: 'مقابلة التعريف');
      return;
    }

    _saving.value = true;
    try {
      final count = await _repo.save(ws, _projectId, answers);
      UiFeedback.success(
        'حفظنا أساس مشروعك ($count حقلاً). ستقترحه الأدوات تلقائياً الآن.',
        title: 'مقابلة التعريف',
      );
      if (mounted) Navigator.of(context).maybePop();
    } on ApiException catch (e) {
      UiFeedback.error(e.message, title: 'مقابلة التعريف');
    } finally {
      _saving.value = false;
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Scaffold(
      appBar: AppBar(title: const Text('مقابلة التعريف')),
      floatingActionButton: Obx(() => FloatingActionButton.extended(
            onPressed: _saving.value ? null : _save,
            icon: _saving.value
                ? const SizedBox(
                    height: 18,
                    width: 18,
                    child: CircularProgressIndicator(strokeWidth: 2.2))
                : const Icon(Icons.save_outlined),
            label: Text(_saving.value ? 'جارٍ الحفظ...' : 'احفظ الأساس'),
          )),
      body: Obx(() {
        if (_loading.value) return AppStateView.loading();
        if (_error.value != null) {
          return AppStateView.error(message: _error.value, onRetry: _load);
        }
        return ListView(
          padding: const EdgeInsets.fromLTRB(16, 16, 16, 96),
          children: [
            Card(
              color: theme.colorScheme.secondaryContainer,
              child: Padding(
                padding: const EdgeInsets.all(14),
                child: Row(
                  children: [
                    Icon(Icons.record_voice_over_outlined,
                        color: theme.colorScheme.primary),
                    const SizedBox(width: 10),
                    Expanded(
                      child: Text(
                        'أجب بلغتك عن هذه الأسئلة القليلة. سنحفظها كأساس لمشروعك '
                        'وستقترحها الأدوات عليك تلقائياً.',
                        style: theme.textTheme.bodyMedium,
                      ),
                    ),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 12),
            ..._questions.map((q) => Padding(
                  padding: const EdgeInsets.only(bottom: 18),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(q.label,
                          style: theme.textTheme.titleSmall
                              ?.copyWith(fontWeight: FontWeight.w700)),
                      if (q.hint.isNotEmpty) ...[
                        const SizedBox(height: 4),
                        Text(q.hint,
                            style: theme.textTheme.bodySmall
                                ?.copyWith(color: theme.colorScheme.outline)),
                      ],
                      const SizedBox(height: 8),
                      TextField(
                        controller: _controllers[q.key],
                        minLines: 2,
                        maxLines: 5,
                        decoration: InputDecoration(hintText: q.placeholder),
                      ),
                      if (_voiceEnabled.value)
                        Align(
                          alignment: AlignmentDirectional.centerStart,
                          child: VoiceInputButton(
                            onRecorded: (path) => _transcribeInto(q.key, path),
                          ),
                        ),
                    ],
                  ),
                )),
          ],
        );
      }),
    );
  }
}
