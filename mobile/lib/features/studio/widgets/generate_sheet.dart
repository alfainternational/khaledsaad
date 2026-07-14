import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:get/get.dart';

import '../../../core/error/api_exception.dart';
import '../../../data/models/project_model.dart';
import '../../../data/models/studio_models.dart';
import '../../../data/repositories/project_repository.dart';
import '../studio_controller.dart';

/// ورقة سفلية لتوليد مخرج من قالب: ملخّص اختياري ثم توليد.
class GenerateSheet extends StatefulWidget {
  const GenerateSheet({
    super.key,
    required this.controller,
    required this.template,
    this.initialBrief,
  });

  final StudioController controller;
  final AiTemplate template;
  final String? initialBrief;

  @override
  State<GenerateSheet> createState() => _GenerateSheetState();
}

class _GenerateSheetState extends State<GenerateSheet> {
  final _brief = TextEditingController();
  final _error = RxnString();

  /// رسائل تقدم متدرجة أثناء التوليد — تخبر المستخدم ماذا يحدث فعلاً.
  static const _progressStages = [
    'جارٍ قراءة بيانات مشروعك...',
    'يبني السياق من أدواتك المكتملة...',
    'يكتب المخرج الآن...',
    'يراجع الجودة قبل التسليم...',
    'اللمسات الأخيرة...',
  ];
  final _progressIndex = 0.obs;
  Timer? _progressTimer;

  /// ربط اختياري بمشروع — فيقرأ التوليد بيانات المشروع (workspace_data) كسياق
  /// بدل أن يبدأ من الصفر على مستوى المساحة فقط.
  final _projects = <ProjectModel>[].obs;
  final _projectId = RxnString();

  @override
  void initState() {
    super.initState();
    final initialBrief = widget.initialBrief?.trim();
    if (initialBrief != null && initialBrief.isNotEmpty) {
      _brief.text = initialBrief;
    }
    _loadProjects();
  }

  Future<void> _loadProjects() async {
    final ws = widget.controller.workspaceId;
    if (ws == null) return;
    try {
      final rows = await Get.find<ProjectRepository>().list(ws);
      if (mounted) _projects.assignAll(rows);
    } on ApiException catch (_) {
      // الربط اختياري — نتجاهل فشل جلب المشاريع.
    }
  }

  @override
  void dispose() {
    _progressTimer?.cancel();
    _brief.dispose();
    super.dispose();
  }

  void _startProgressTicker() {
    _progressIndex.value = 0;
    _progressTimer?.cancel();
    _progressTimer = Timer.periodic(const Duration(seconds: 7), (_) {
      if (_progressIndex.value < _progressStages.length - 1) {
        _progressIndex.value++;
      }
    });
  }

  Future<void> _generate({bool freshCopy = false}) async {
    _error.value = null;
    HapticFeedback.mediumImpact(); // إحساس ببدء التوليد
    _startProgressTicker();
    try {
      final generation = await widget.controller.generate(
        templateId: widget.template.id,
        projectPublicId: _projectId.value,
        brief: _brief.text.trim().isEmpty ? null : _brief.text.trim(),
        freshCopy: freshCopy,
      );
      if (!mounted) return; // الورقة أُغلقت أثناء الانتظار — تفادَ pop خاطئ
      if (generation != null) {
        HapticFeedback.lightImpact(); // نجاح التوليد
        Get.back(); // إغلاق الورقة
        widget.controller.openGeneration(generation);
      }
    } on ApiException catch (e) {
      if (e.isCreditsExhausted) {
        _error.value = 'انتهى رصيد الذكاء الاصطناعي. رقِّ باقتك للمتابعة.';
      } else {
        _error.value = e.message;
      }
    } finally {
      _progressTimer?.cancel();
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Padding(
      padding: EdgeInsets.only(
        left: 20,
        right: 20,
        top: 20,
        bottom: MediaQuery.of(context).viewInsets.bottom + 20,
      ),
      child: SingleChildScrollView(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Center(
              child: Container(
                margin: const EdgeInsets.only(bottom: 12),
                width: 40,
                height: 4,
                decoration: BoxDecoration(
                  color: theme.colorScheme.outlineVariant,
                  borderRadius: BorderRadius.circular(999),
                ),
              ),
            ),
            Text(
              widget.template.name,
              style: theme.textTheme.titleLarge?.copyWith(
                fontWeight: FontWeight.w800,
              ),
            ),
          if (widget.template.description != null) ...[
            const SizedBox(height: 6),
            Text(
              widget.template.description!,
              style: theme.textTheme.bodyMedium,
            ),
          ],
          const SizedBox(height: 16),
          Obx(() {
            if (_projects.isEmpty) return const SizedBox.shrink();
            return Padding(
              padding: const EdgeInsets.only(bottom: 12),
              child: DropdownButtonFormField<String?>(
                initialValue: _projectId.value,
                isExpanded: true,
                decoration: const InputDecoration(
                  labelText: 'اربط بمشروع (اختياري)',
                ),
                items: [
                  const DropdownMenuItem<String?>(
                    value: null,
                    child: Text('بدون مشروع'),
                  ),
                  ..._projects.map((p) => DropdownMenuItem<String?>(
                        value: p.publicId,
                        child: Text(p.name, overflow: TextOverflow.ellipsis),
                      )),
                ],
                onChanged: (v) => _projectId.value = v,
              ),
            );
          }),
          TextField(
            controller: _brief,
            minLines: 3,
            maxLines: 6,
            decoration: const InputDecoration(
              labelText: 'ملخّص (اختياري)',
              hintText: 'أي توجيه إضافي تريده لهذا المخرج؟',
            ),
          ),
          const SizedBox(height: 8),
          Obx(() {
            final err = _error.value;
            if (err == null) return const SizedBox.shrink();
            return Padding(
              padding: const EdgeInsets.only(bottom: 8),
              child: Text(
                err,
                style: TextStyle(color: theme.colorScheme.error),
              ),
            );
          }),
          if (widget.template.creditCost != null)
            Padding(
              padding: const EdgeInsets.only(bottom: 10),
              child: Row(
                children: [
                  Icon(Icons.bolt_outlined,
                      size: 16, color: theme.colorScheme.primary),
                  const SizedBox(width: 4),
                  Text(
                    'تكلفة هذا التوليد: ${widget.template.creditCost} رصيد',
                    style: theme.textTheme.bodySmall?.copyWith(
                      color: theme.colorScheme.onSurfaceVariant,
                    ),
                  ),
                ],
              ),
            ),
          Obx(() {
            final generating = widget.controller.isGenerating.value;
            return Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                if (generating) ...[
                  Padding(
                    padding: const EdgeInsets.only(bottom: 8),
                    child: Row(
                      children: [
                        const SizedBox(
                          height: 16,
                          width: 16,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        ),
                        const SizedBox(width: 8),
                        Expanded(
                          child: Obx(() => Text(
                                _progressStages[_progressIndex.value],
                                style: theme.textTheme.bodySmall?.copyWith(
                                  color: theme.colorScheme.primary,
                                ),
                              )),
                        ),
                      ],
                    ),
                  ),
                ],
                FilledButton.icon(
                  onPressed: generating ? null : _generate,
                  icon: generating
                      ? const SizedBox(
                          height: 20,
                          width: 20,
                          child: CircularProgressIndicator(strokeWidth: 2.2),
                        )
                      : const Icon(Icons.auto_awesome),
                  label: Text(generating ? 'جارٍ التوليد...' : 'توليد'),
                ),
                if (!generating)
                  TextButton.icon(
                    onPressed: () => _generate(freshCopy: true),
                    icon: const Icon(Icons.refresh, size: 18),
                    label: const Text('توليد نسخة جديدة (يتجاوز الكاش)'),
                  ),
              ],
            );
          }),
          ],
        ),
      ),
    );
  }
}
