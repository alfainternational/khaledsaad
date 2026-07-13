import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../data/models/studio_models.dart';
import '../../data/repositories/studio_repository.dart';
import '../../data/services/workspace_service.dart';
import '../shared/widgets/action_tile.dart';
import '../shared/widgets/animated_app_background.dart';
import '../shared/widgets/app_state_view.dart';
import '../shared/widgets/hero_panel.dart';
import 'studio_controller.dart';
import 'widgets/generate_sheet.dart';

class StudioPage extends StatelessWidget {
  const StudioPage({super.key});

  @override
  Widget build(BuildContext context) {
    final c = Get.put(
      StudioController(
        Get.find<StudioRepository>(),
        Get.find<WorkspaceService>(),
      ),
    );
    final theme = Theme.of(context);
    final args = Get.arguments;
    final initialBrief = args is Map ? args['brief']?.toString() : null;
    final sourceTitle = args is Map ? args['source_title']?.toString() : null;

    return Scaffold(
      appBar: AppBar(title: const Text('الاستوديو الذكي')),
      body: AnimatedAppBackground(
        child: Obx(() {
          if (c.isLoading.value &&
              c.templates.isEmpty &&
              c.generations.isEmpty) {
            return AppStateView.loading();
          }
          if (c.error.value != null &&
              c.templates.isEmpty &&
              c.generations.isEmpty) {
            return AppStateView.error(message: c.error.value, onRetry: c.load);
          }
          return RefreshIndicator(
            onRefresh: c.load,
            child: ListView(
              padding: const EdgeInsets.fromLTRB(16, 12, 16, 28),
              children: [
                const HeroPanel(
                  icon: Icons.auto_awesome,
                  title: 'حوّل التشخيص إلى مخرجات جاهزة',
                  body:
                      'اختر قالباً مناسباً، وأضف موجز التنفيذ لتحصل على محتوى أو صفحة أو حملة قابلة للاستخدام.',
                ),
                const SizedBox(height: 12),
                Card(
                  child: Padding(
                    padding: const EdgeInsets.all(14),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'كيف يعمل الاستوديو؟',
                          style: theme.textTheme.titleSmall
                              ?.copyWith(fontWeight: FontWeight.w800),
                        ),
                        const SizedBox(height: 8),
                        const _StudioStep(
                          number: '1',
                          text:
                              'القالب هو وصفة جاهزة لمخرج تسويقي محدد: إعلان، تسلسل إيميل، رسائل واتساب، خطة محتوى...',
                        ),
                        const _StudioStep(
                          number: '2',
                          text:
                              'اختر قالباً من القائمة أدناه، وأضف ملاحظاتك إن أردت، ثم اضغط «توليد».',
                        ),
                        const _StudioStep(
                          number: '3',
                          text:
                              'يقرأ الاستوديو بيانات مشروعك من الأدوات التي أكملتها ويسلّمك مخرجاً جاهزاً للاستخدام يظهر في «أحدث المخرجات».',
                        ),
                      ],
                    ),
                  ),
                ),
                const SizedBox(height: 16),
                if (initialBrief != null && initialBrief.trim().isNotEmpty) ...[
                  Card(
                    child: ListTile(
                      leading: Icon(
                        Icons.auto_awesome,
                        color: theme.colorScheme.primary,
                      ),
                      title: const Text('موجز التنفيذ جاهز'),
                      subtitle: Text(
                        sourceTitle?.trim().isNotEmpty == true
                            ? 'سيتم تعبئة التوليد من: $sourceTitle'
                            : 'سيتم تعبئة التوليد من حزمة التنفيذ.',
                      ),
                    ),
                  ),
                  const SizedBox(height: 16),
                ],
                Text(
                  'القوالب',
                  style: theme.textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 8),
                if (c.templates.isEmpty)
                  Card(
                    child: Padding(
                      padding: const EdgeInsets.all(14),
                      child: Column(
                        children: [
                          Icon(Icons.description_outlined,
                              size: 36,
                              color: theme.colorScheme.primary
                                  .withValues(alpha: 0.6)),
                          const SizedBox(height: 8),
                          Text(
                            'تعذر تحميل القوالب حالياً. اسحب الشاشة للأسفل للتحديث، وإن استمرت المشكلة تواصل مع الدعم.',
                            style: theme.textTheme.bodyMedium,
                            textAlign: TextAlign.center,
                          ),
                          const SizedBox(height: 10),
                          OutlinedButton.icon(
                            onPressed: c.load,
                            icon: const Icon(Icons.refresh, size: 18),
                            label: const Text('إعادة التحميل'),
                          ),
                        ],
                      ),
                    ),
                  )
                else
                  ...c.templates.map(
                    (t) => ActionTile(
                      icon: Icons.description_outlined,
                      title: t.name,
                      subtitle:
                          t.description ?? 'قالب جاهز للتوليد من سياق مشروعك',
                      badge: t.creditCost != null
                          ? '${t.creditCost} رصيد'
                          : null,
                      onTap: () => _openGenerate(
                        context,
                        c,
                        t,
                        initialBrief: initialBrief,
                      ),
                    ),
                  ),
                const SizedBox(height: 24),
                Text(
                  'أحدث المخرجات',
                  style: theme.textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 8),
                if (c.generations.isEmpty)
                  Padding(
                    padding: const EdgeInsets.symmetric(vertical: 12),
                    child: Text(
                      'لم تُنشئ أي مخرجات بعد. اختر قالباً من قائمة القوالب أعلاه واضغط «توليد» — وسيظهر المخرج هنا.',
                      style: theme.textTheme.bodyMedium,
                    ),
                  )
                else
                  ...c.generations.map(
                    (g) => _GenerationTile(
                      generation: g,
                      onTap: () => c.openGeneration(g),
                      onDelete: () => _confirmDelete(context, c, g),
                    ),
                  ),
              ],
            ),
          );
        }),
      ),
    );
  }

  void _openGenerate(
    BuildContext context,
    StudioController c,
    AiTemplate template, {
    String? initialBrief,
  }) {
    showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      builder: (_) => GenerateSheet(
        controller: c,
        template: template,
        initialBrief: initialBrief,
      ),
    );
  }

  Future<void> _confirmDelete(
    BuildContext context,
    StudioController c,
    StudioGeneration g,
  ) async {
    final ok = await showDialog<bool>(
      context: context,
      builder: (_) => AlertDialog(
        title: const Text('حذف المخرج'),
        content: const Text('هل تريد حذف هذا المخرج نهائياً؟'),
        actions: [
          TextButton(
            onPressed: () => Get.back(result: false),
            child: const Text('إلغاء'),
          ),
          FilledButton(
            onPressed: () => Get.back(result: true),
            child: const Text('حذف'),
          ),
        ],
      ),
    );
    if (ok == true) {
      await c.deleteGeneration(g);
    }
  }
}

/// خطوة مرقّمة في بطاقة «كيف يعمل الاستوديو؟».
class _StudioStep extends StatelessWidget {
  const _StudioStep({required this.number, required this.text});

  final String number;
  final String text;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 22,
            height: 22,
            alignment: Alignment.center,
            decoration: BoxDecoration(
              color: theme.colorScheme.primary.withValues(alpha: 0.12),
              shape: BoxShape.circle,
            ),
            child: Text(
              number,
              style: theme.textTheme.labelSmall?.copyWith(
                color: theme.colorScheme.primary,
                fontWeight: FontWeight.w800,
              ),
            ),
          ),
          const SizedBox(width: 8),
          Expanded(
            child: Text(text,
                style: theme.textTheme.bodySmall?.copyWith(height: 1.6)),
          ),
        ],
      ),
    );
  }
}

class _GenerationTile extends StatelessWidget {
  const _GenerationTile({
    required this.generation,
    required this.onTap,
    required this.onDelete,
  });

  final StudioGeneration generation;
  final VoidCallback onTap;
  final VoidCallback onDelete;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: ListTile(
        leading: Icon(
          generation.isFailed
              ? Icons.error_outline
              : Icons.description_outlined,
          color: generation.isFailed
              ? Theme.of(context).colorScheme.error
              : Theme.of(context).colorScheme.primary,
        ),
        title: Text(generation.templateName ?? 'مخرج'),
        subtitle: Text(generation.createdAt ?? generation.status),
        trailing: IconButton(
          icon: const Icon(Icons.delete_outline),
          onPressed: onDelete,
        ),
        onTap: onTap,
      ),
    );
  }
}
