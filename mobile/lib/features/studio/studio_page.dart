import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../data/models/studio_models.dart';
import '../../data/repositories/studio_repository.dart';
import '../../data/services/workspace_service.dart';
import '../shared/widgets/app_state_view.dart';
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
      body: Obx(() {
        if (c.isLoading.value && c.templates.isEmpty && c.generations.isEmpty) {
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
            padding: const EdgeInsets.all(16),
            children: [
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
                const Padding(
                  padding: EdgeInsets.symmetric(vertical: 12),
                  child: Text('لا توجد قوالب متاحة حالياً.'),
                )
              else
                ...c.templates.map(
                  (t) => _TemplateTile(
                    template: t,
                    onGenerate: () => _openGenerate(
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
                const Padding(
                  padding: EdgeInsets.symmetric(vertical: 12),
                  child: Text('لم تُنشئ أي مخرجات بعد.'),
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

class _TemplateTile extends StatelessWidget {
  const _TemplateTile({required this.template, required this.onGenerate});

  final AiTemplate template;
  final VoidCallback onGenerate;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: ListTile(
        title: Text(template.name),
        subtitle: template.description != null
            ? Text(
                template.description!,
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
              )
            : null,
        trailing: FilledButton.tonal(
          onPressed: onGenerate,
          child: const Text('توليد'),
        ),
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
