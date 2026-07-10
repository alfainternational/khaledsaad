import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../data/models/project_model.dart';
import '../../data/repositories/project_repository.dart';
import '../../data/services/workspace_service.dart';
import '../shared/widgets/app_state_view.dart';
import '../shared/widgets/status_badge.dart';
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
      body: Column(
        children: [
          _StatusFilterBar(controller: c),
          Expanded(
            child: Obx(() {
              if (c.isLoading.value && c.projects.isEmpty) {
                return AppStateView.loading();
              }
              if (c.error.value != null && c.projects.isEmpty) {
                return AppStateView.error(message: c.error.value, onRetry: c.load);
              }
              if (c.projects.isEmpty) {
                return AppStateView.empty(
                  icon: Icons.folder_off_outlined,
                  title: 'لا توجد مشاريع',
                  message: 'أنشئ مشروعك الأول من الويب أو من زر الإضافة.',
                );
              }
              return RefreshIndicator(
                onRefresh: c.load,
                child: ListView.separated(
                  padding: const EdgeInsets.all(16),
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
                      style: theme.textTheme.titleMedium
                          ?.copyWith(fontWeight: FontWeight.w700),
                    ),
                  ),
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
                    Text(project.client!.name, style: theme.textTheme.bodySmall),
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
