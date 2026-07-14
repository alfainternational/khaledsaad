import 'package:cached_network_image/cached_network_image.dart';
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
                              // صورة مُكاشة محلياً (أسرع وأقل استهلاكاً للبيانات).
                              child: CachedNetworkImage(
                                imageUrl: project.logoUrl!,
                                width: 54,
                                height: 54,
                                fit: BoxFit.cover,
                                progressIndicatorBuilder: (_, _, _) => Container(
                                  width: 54,
                                  height: 54,
                                  alignment: Alignment.center,
                                  color: theme.colorScheme.onPrimary
                                      .withValues(alpha: 0.15),
                                  child: SizedBox(
                                    width: 20,
                                    height: 20,
                                    child: CircularProgressIndicator(
                                      strokeWidth: 2,
                                      color: theme.colorScheme.onPrimary,
                                    ),
                                  ),
                                ),
                                // فشل الصورة: نعرض بديلاً يملأ الحيز حتى لا
                                // يبقى الفاصل معلّقاً بجوار فراغ.
                                errorWidget: (_, _, _) => Container(
                                  width: 54,
                                  height: 54,
                                  alignment: Alignment.center,
                                  color: theme.colorScheme.onPrimary
                                      .withValues(alpha: 0.15),
                                  child: Icon(
                                    Icons.image_not_supported_outlined,
                                    color: theme.colorScheme.onPrimary,
                                  ),
                                ),
                              ),
                            ),
                            const SizedBox(width: 12),
                          ],
                          Expanded(
                            child: Text(
                              project.name,
                              maxLines: 2,
                              overflow: TextOverflow.ellipsis,
                              style: theme.textTheme.titleLarge?.copyWith(
                                color: theme.colorScheme.onPrimary,
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
                if (project.executionSummary != null)
                  Builder(builder: (_) {
                    final s = project.executionSummary!;
                    final pct =
                        (s['task_progress_percent'] as num?)?.toInt() ?? 0;
                    final packages =
                        (s['packages_count'] as num?)?.toInt() ?? 0;
                    final done = (s['done_tasks'] as num?)?.toInt() ?? 0;
                    final total = (s['total_tasks'] as num?)?.toInt() ?? 0;
                    final measure = s['latest_measurement'];
                    final phaseLabel = measure is Map
                        ? measure['phase_label']?.toString()
                        : null;
                    if (packages == 0) return const SizedBox.shrink();
                    return Card(
                      margin: const EdgeInsets.only(bottom: 16),
                      child: Padding(
                        padding: const EdgeInsets.all(16),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Row(
                              children: [
                                Icon(Icons.rocket_launch_outlined,
                                    size: 18,
                                    color: theme.colorScheme.primary),
                                const SizedBox(width: 8),
                                Text('ملخّص التنفيذ',
                                    style: theme.textTheme.titleSmall
                                        ?.copyWith(
                                            fontWeight: FontWeight.w800)),
                              ],
                            ),
                            const SizedBox(height: 12),
                            ClipRRect(
                              borderRadius: BorderRadius.circular(6),
                              child: LinearProgressIndicator(
                                value: (pct / 100).clamp(0.0, 1.0),
                                minHeight: 8,
                              ),
                            ),
                            const SizedBox(height: 8),
                            Text(
                              '$packages حزمة · $done من $total مهمة ($pct%)'
                              '${phaseLabel != null ? ' · آخر قياس: $phaseLabel' : ''}',
                              style: theme.textTheme.bodySmall,
                            ),
                          ],
                        ),
                      ),
                    );
                  }),
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
                ActionTile(
                  icon: Icons.folder_open_outlined,
                  title: 'مصادر المعرفة',
                  subtitle: 'ارفع مستنداتك ليقرأها التحليل ويضمّنها في تقاريرك',
                  onTap: () =>
                      Get.toNamed(Routes.knowledge, arguments: publicId),
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
