import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../app/routes/app_routes.dart';
import '../../data/repositories/project_repository.dart';
import '../../data/services/workspace_service.dart';
import '../shared/widgets/app_state_view.dart';
import '../shared/widgets/status_badge.dart';
import 'project_detail_controller.dart';

/// شاشة المشروع — تصميم هادئ: بطاقة تعريف + أربع بوابات واضحة
/// (الأدوات، ملف المشروع، التحليل والتوصيات، التقارير). لا زحمة.
class ProjectDetailPage extends StatelessWidget {
  const ProjectDetailPage({super.key});

  @override
  Widget build(BuildContext context) {
    final publicId = Get.arguments as String;
    final c = Get.put(
      ProjectDetailController(
        Get.find<ProjectRepository>(),
        Get.find<WorkspaceService>(),
        publicId,
      ),
      tag: publicId,
    );

    return Scaffold(
      appBar: AppBar(title: const Text('المشروع')),
      body: Obx(() {
        if (c.isLoading.value && c.project.value == null) {
          return AppStateView.loading();
        }
        if (c.error.value != null && c.project.value == null) {
          return AppStateView.error(message: c.error.value, onRetry: c.load);
        }
        final project = c.project.value;
        if (project == null) {
          return AppStateView.empty(
              icon: Icons.folder_off_outlined, title: 'المشروع غير متاح');
        }
        final theme = Theme.of(context);
        return RefreshIndicator(
          onRefresh: c.load,
          child: ListView(
            padding: const EdgeInsets.all(16),
            children: [
              // بطاقة التعريف
              Card(
                child: Padding(
                  padding: const EdgeInsets.all(16),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          Expanded(
                            child: Text(project.name,
                                style: theme.textTheme.titleLarge
                                    ?.copyWith(fontWeight: FontWeight.w800)),
                          ),
                          StatusBadge(status: project.status),
                        ],
                      ),
                      const SizedBox(height: 8),
                      Wrap(
                        spacing: 16,
                        runSpacing: 4,
                        children: [
                          _Meta(icon: Icons.flag_outlined, text: 'المرحلة ${project.stage}'),
                          if (project.sector != null)
                            _Meta(icon: Icons.category_outlined, text: project.sector!),
                          if (project.client != null)
                            _Meta(icon: Icons.badge_outlined, text: project.client!.name),
                        ],
                      ),
                    ],
                  ),
                ),
              ),
              const SizedBox(height: 16),
              // البوابات الأربع
              _GateTile(
                icon: Icons.build_outlined,
                title: 'الأدوات',
                subtitle: 'اشتغل على مشروعك خطوة بخطوة',
                onTap: () => Get.toNamed(Routes.projectTools, arguments: publicId),
              ),
              _GateTile(
                icon: Icons.description_outlined,
                title: 'ملف المشروع',
                subtitle: 'المعلومات الأساسية التي تغذّي كل الأدوات',
                onTap: () => Get.toNamed(Routes.projectBrief, arguments: publicId),
              ),
              _GateTile(
                icon: Icons.query_stats_outlined,
                title: 'التحليل والتوصيات',
                subtitle: 'حلّل حضورك واحصل على خطوات عملية',
                onTap: () =>
                    Get.toNamed(Routes.projectIntelligence, arguments: publicId),
              ),
              _GateTile(
                icon: Icons.analytics_outlined,
                title: 'التقارير',
                subtitle: 'التقرير الشامل ودليل المشروع + PDF',
                onTap: () => Get.toNamed(Routes.projectReports, arguments: publicId),
              ),
            ],
          ),
        );
      }),
    );
  }
}

class _Meta extends StatelessWidget {
  const _Meta({required this.icon, required this.text});

  final IconData icon;
  final String text;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Icon(icon, size: 16, color: theme.colorScheme.primary),
        const SizedBox(width: 4),
        Text(text, style: theme.textTheme.bodySmall),
      ],
    );
  }
}

class _GateTile extends StatelessWidget {
  const _GateTile({
    required this.icon,
    required this.title,
    required this.subtitle,
    required this.onTap,
  });

  final IconData icon;
  final String title;
  final String subtitle;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Card(
      margin: const EdgeInsets.only(bottom: 10),
      child: ListTile(
        contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 6),
        leading: CircleAvatar(
          backgroundColor: theme.colorScheme.primary.withValues(alpha: 0.1),
          child: Icon(icon, color: theme.colorScheme.primary),
        ),
        title: Text(title,
            style:
                theme.textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w700)),
        subtitle: Text(subtitle, style: theme.textTheme.bodySmall),
        trailing: const Icon(Icons.chevron_left),
        onTap: onTap,
      ),
    );
  }
}
