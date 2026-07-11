import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../app/routes/app_routes.dart';
import '../../data/repositories/project_repository.dart';
import '../../data/services/workspace_service.dart';
import '../shared/widgets/action_tile.dart';
import '../shared/widgets/animated_app_background.dart';
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
      body: AnimatedAppBackground(
        child: Obx(() {
          if (c.isLoading.value && c.project.value == null) {
            return AppStateView.loading();
          }
          if (c.error.value != null && c.project.value == null) {
            return AppStateView.error(message: c.error.value, onRetry: c.load);
          }
          final project = c.project.value;
          if (project == null) {
            return AppStateView.empty(
              icon: Icons.folder_off_outlined,
              title: 'المشروع غير متاح',
            );
          }
          final theme = Theme.of(context);
          return RefreshIndicator(
            onRefresh: c.load,
            child: ListView(
              padding: const EdgeInsets.fromLTRB(16, 12, 16, 28),
              children: [
                Container(
                  padding: const EdgeInsets.all(18),
                  decoration: BoxDecoration(
                    color: theme.colorScheme.primary,
                    borderRadius: BorderRadius.circular(24),
                    boxShadow: [
                      BoxShadow(
                        color: theme.colorScheme.primary.withValues(
                          alpha: 0.22,
                        ),
                        blurRadius: 22,
                        offset: const Offset(0, 12),
                      ),
                    ],
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          if (project.logoUrl?.isNotEmpty == true) ...[
                            ClipRRect(
                              borderRadius: BorderRadius.circular(10),
                              child: Image.network(
                                project.logoUrl!,
                                width: 54,
                                height: 54,
                                fit: BoxFit.cover,
                                errorBuilder: (_, _, _) =>
                                    const SizedBox.shrink(),
                              ),
                            ),
                            const SizedBox(width: 12),
                          ],
                          Expanded(
                            child: Text(
                              project.name,
                              style: theme.textTheme.titleLarge?.copyWith(
                                color: Colors.white,
                                fontWeight: FontWeight.w900,
                              ),
                            ),
                          ),
                          StatusBadge(status: project.status),
                        ],
                      ),
                      const SizedBox(height: 8),
                      Wrap(
                        spacing: 16,
                        runSpacing: 4,
                        children: [
                          _Meta(
                            icon: Icons.flag_outlined,
                            text: 'المرحلة ${project.stage}',
                          ),
                          if (project.sector != null)
                            _Meta(
                              icon: Icons.category_outlined,
                              text: project.sector!,
                            ),
                          if (project.client != null)
                            _Meta(
                              icon: Icons.badge_outlined,
                              text: project.client!.name,
                            ),
                        ],
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 16),
                Text(
                  'الخطوات العملية',
                  style: theme.textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 8),
                ActionTile(
                  icon: Icons.build_outlined,
                  title: 'الأدوات',
                  subtitle: 'ابدأ بالأداة المناسبة حسب وضع مشروعك الحالي',
                  emphasized: true,
                  badge: 'التالي',
                  onTap: () =>
                      Get.toNamed(Routes.projectTools, arguments: publicId),
                ),
                ActionTile(
                  icon: Icons.description_outlined,
                  title: 'ملف المشروع',
                  subtitle: 'المعلومات الأساسية التي تغذّي كل الأدوات',
                  onTap: () =>
                      Get.toNamed(Routes.projectBrief, arguments: publicId),
                ),
                ActionTile(
                  icon: Icons.query_stats_outlined,
                  title: 'التحليل والتوصيات',
                  subtitle: 'حلّل حضورك واحصل على خطوات عملية',
                  onTap: () => Get.toNamed(
                    Routes.projectIntelligence,
                    arguments: publicId,
                  ),
                ),
                ActionTile(
                  icon: Icons.analytics_outlined,
                  title: 'التقارير',
                  subtitle: 'التقرير الشامل ودليل المشروع + PDF',
                  onTap: () =>
                      Get.toNamed(Routes.projectReports, arguments: publicId),
                ),
              ],
            ),
          );
        }),
      ),
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
