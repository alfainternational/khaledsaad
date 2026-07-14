import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:get/get.dart';

import '../../core/error/api_exception.dart';
import '../../data/models/project_model.dart';
import '../../data/repositories/project_repository.dart';
import '../../data/services/workspace_service.dart';
import '../shared/widgets/app_state_view.dart';
import '../shared/widgets/status_badge.dart';
import '../shared/widgets/ui_feedback.dart';
import 'projects_controller.dart';

class ProjectsPage extends StatelessWidget {
  const ProjectsPage({super.key});

  @override
  Widget build(BuildContext context) {
    final c = Get.put(ProjectsController(
      Get.find<ProjectRepository>(),
      Get.find<WorkspaceService>(),
    ));

    return Scaffold(
      appBar: AppBar(title: const Text('مشاريعي')),
      // startFloat (يمين في RTL) كي لا يتراكب مع زر المساعد العائم (يسار).
      floatingActionButtonLocation: FloatingActionButtonLocation.startFloat,
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () => _createProject(context, c),
        icon: const Icon(Icons.add),
        label: const Text('مشروع جديد'),
      ),
      body: Column(
        children: [
          _StatusFilterBar(controller: c),
          _StageFilterBar(controller: c),
          Expanded(
            child: Obx(() {
              if (c.isLoading.value && c.projects.isEmpty) {
                return AppStateView.skeleton();
              }
              if (c.error.value != null && c.projects.isEmpty) {
                return AppStateView.error(message: c.error.value, onRetry: c.load);
              }
              if (c.projects.isEmpty) {
                return AppStateView.empty(
                  icon: Icons.folder_off_outlined,
                  title: 'لا توجد مشاريع',
                  message: 'ابدأ بإنشاء مشروعك الأول ثم شغّل الأداة المناسبة.',
                  actionLabel: 'أنشئ مشروعاً',
                  onAction: () => _createProject(context, c),
                );
              }
              return RefreshIndicator(
                onRefresh: c.load,
                child: ListView.separated(
                  padding: const EdgeInsets.fromLTRB(16, 16, 16, 88),
                  itemCount: c.projects.length,
                  separatorBuilder: (_, _) => const SizedBox(height: 12),
                  itemBuilder: (_, i) => _ProjectCard(
                    project: c.projects[i],
                    onTap: () => c.openProject(c.projects[i]),
                  ),
                ),
              );
            }),
          ),
        ],
      ),
    );
  }

  /// ورقة سفلية بسيطة لإنشاء مشروع (الاسم + القطاع)، ثم تُنشئ عبر الكنترولر.
  Future<void> _createProject(BuildContext context, ProjectsController c) async {
    HapticFeedback.selectionClick();
    final nameController = TextEditingController();
    final sector = ValueNotifier<String>(ProjectsController.sectors.keys.first);
    final saving = ValueNotifier<bool>(false);

    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (sheetContext) {
        final theme = Theme.of(sheetContext);
        return Padding(
          padding: EdgeInsets.only(
            left: 16,
            right: 16,
            top: 8,
            bottom: MediaQuery.of(sheetContext).viewInsets.bottom + 16,
          ),
          child: SingleChildScrollView(
            child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text(
                'مشروع جديد',
                style: theme.textTheme.titleMedium
                    ?.copyWith(fontWeight: FontWeight.w800),
              ),
              const SizedBox(height: 4),
              Text(
                'ابدأ بالاسم والقطاع — تكمل بقية التفاصيل لاحقاً من ملف المشروع.',
                style: theme.textTheme.bodySmall,
              ),
              const SizedBox(height: 16),
              TextField(
                controller: nameController,
                textInputAction: TextInputAction.done,
                autofocus: true,
                decoration: const InputDecoration(
                  labelText: 'اسم المشروع',
                  prefixIcon: Icon(Icons.folder_outlined),
                ),
              ),
              const SizedBox(height: 12),
              ValueListenableBuilder<String>(
                valueListenable: sector,
                builder: (_, value, _) => DropdownButtonFormField<String>(
                  initialValue: value,
                  decoration: const InputDecoration(
                    labelText: 'القطاع',
                    prefixIcon: Icon(Icons.category_outlined),
                  ),
                  items: ProjectsController.sectors.entries
                      .map((e) => DropdownMenuItem(
                            value: e.key,
                            child: Text(e.value),
                          ))
                      .toList(),
                  onChanged: (v) {
                    if (v != null) sector.value = v;
                  },
                ),
              ),
              const SizedBox(height: 20),
              ValueListenableBuilder<bool>(
                valueListenable: saving,
                builder: (_, isSaving, _) => FilledButton.icon(
                  onPressed: isSaving
                      ? null
                      : () async {
                          final name = nameController.text.trim();
                          if (name.isEmpty) {
                            UiFeedback.error('اكتب اسم المشروع أولاً.');
                            return;
                          }
                          saving.value = true;
                          try {
                            final project = await c.create(
                              name: name,
                              sector: sector.value,
                            );
                            if (sheetContext.mounted) {
                              Navigator.of(sheetContext).pop();
                            }
                            UiFeedback.success('تم إنشاء المشروع.');
                            c.openProject(project);
                          } on ApiException catch (e) {
                            saving.value = false;
                            UiFeedback.error(e.message);
                          }
                        },
                  icon: isSaving
                      ? const SizedBox(
                          height: 18,
                          width: 18,
                          child: CircularProgressIndicator(strokeWidth: 2.2),
                        )
                      : const Icon(Icons.check),
                  label: Text(isSaving ? 'جارٍ الإنشاء...' : 'أنشئ المشروع'),
                ),
              ),
            ],
          ),
          ),
        );
      },
    );

    nameController.dispose();
    sector.dispose();
    saving.dispose();
  }
}

/// شريط فلترة المرحلة (1..5) — يُفعّل stageFilter في الكنترولر.
class _StageFilterBar extends StatelessWidget {
  const _StageFilterBar({required this.controller});

  final ProjectsController controller;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 48,
      child: Obx(() => ListView(
            scrollDirection: Axis.horizontal,
            padding: const EdgeInsets.symmetric(horizontal: 12),
            children: [
              _stageChip(context, null, 'كل المراحل'),
              for (var s = 1; s <= 5; s++)
                _stageChip(context, s, 'مرحلة $s'),
            ],
          )),
    );
  }

  Widget _stageChip(BuildContext context, int? stage, String label) {
    final selected = controller.stageFilter.value == stage;
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 4),
      child: ChoiceChip(
        label: Text(label),
        selected: selected,
        onSelected: (_) => controller.setStage(stage),
      ),
    );
  }
}

class _StatusFilterBar extends StatelessWidget {
  const _StatusFilterBar({required this.controller});

  final ProjectsController controller;

  static const _statuses = <String, String>{
    '': 'الكل',
    'active': 'نشط',
    'paused': 'متوقف',
    'completed': 'مكتمل',
    'archived': 'مؤرشف',
  };

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 56,
      child: Obx(() => ListView(
            scrollDirection: Axis.horizontal,
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
            children: _statuses.entries.map((e) {
              final selected = (controller.statusFilter.value ?? '') == e.key;
              return Padding(
                padding: const EdgeInsets.symmetric(horizontal: 4),
                child: ChoiceChip(
                  label: Text(e.value),
                  selected: selected,
                  onSelected: (_) =>
                      controller.setStatus(e.key.isEmpty ? null : e.key),
                ),
              );
            }).toList(),
          )),
    );
  }
}

class _ProjectCard extends StatelessWidget {
  const _ProjectCard({required this.project, required this.onTap});

  final ProjectModel project;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Card(
      child: InkWell(
        borderRadius: BorderRadius.circular(16),
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Expanded(
                    child: Text(
                      project.name,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: theme.textTheme.titleMedium
                          ?.copyWith(fontWeight: FontWeight.w700),
                    ),
                  ),
                  const SizedBox(width: 8),
                  StatusBadge(status: project.status),
                ],
              ),
              const SizedBox(height: 8),
              Row(
                children: [
                  Icon(Icons.flag_outlined,
                      size: 16, color: theme.colorScheme.primary),
                  const SizedBox(width: 4),
                  Text('المرحلة ${project.stage}',
                      style: theme.textTheme.bodySmall),
                  if (project.client != null) ...[
                    const SizedBox(width: 16),
                    Icon(Icons.badge_outlined,
                        size: 16, color: theme.colorScheme.primary),
                    const SizedBox(width: 4),
                    Flexible(
                      child: Text(
                        project.client!.name,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: theme.textTheme.bodySmall,
                      ),
                    ),
                  ],
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}
