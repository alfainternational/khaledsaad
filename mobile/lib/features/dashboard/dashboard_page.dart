import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../app/routes/app_routes.dart';
import '../../data/repositories/auth_repository.dart';
import '../../data/repositories/collab_repository.dart';
import '../../data/services/session_service.dart';
import '../../data/services/workspace_service.dart';
import '../shared/widgets/action_tile.dart';
import '../shared/widgets/animated_app_background.dart';
import '../shared/widgets/app_state_view.dart';
import '../shared/widgets/hero_panel.dart';
import 'dashboard_controller.dart';

class DashboardPage extends StatelessWidget {
  const DashboardPage({super.key});

  @override
  Widget build(BuildContext context) {
    final c = Get.put(
      DashboardController(
        Get.find<WorkspaceService>(),
        Get.find<AuthRepository>(),
        Get.find<SessionService>(),
        Get.find<CollabRepository>(),
      ),
    );

    return Scaffold(
      appBar: AppBar(
        title: const Text('مركز النمو'),
        actions: [
          IconButton(
            tooltip: 'تسجيل الخروج',
            icon: const Icon(Icons.logout),
            onPressed: c.logout,
          ),
        ],
      ),
      body: AnimatedAppBackground(
        child: Obx(() {
          if (c.isLoading.value && c.workspaces.workspaces.isEmpty) {
            return AppStateView.loading();
          }
          if (c.error.value != null && c.workspaces.workspaces.isEmpty) {
            return AppStateView.error(message: c.error.value, onRetry: c.load);
          }
          if (c.workspaces.workspaces.isEmpty) {
            return AppStateView.empty(
              icon: Icons.workspaces_outline,
              title: 'لا توجد مساحات عمل',
              message: 'ابدأ بإنشاء مساحة عمل من الويب.',
            );
          }

          return RefreshIndicator(
            onRefresh: c.load,
            child: ListView(
              padding: const EdgeInsets.fromLTRB(16, 12, 16, 28),
              children: [
                const HeroPanel(
                  icon: Icons.trending_up,
                  title: 'خطوتك التالية تبدأ من هنا',
                  body:
                      'تابع مشروعك، شغّل الأداة المناسبة، وحوّل التشخيص إلى تنفيذ قابل للقياس.',
                ),
                const SizedBox(height: 14),
                Obx(() {
                  final next = c.nextStep;
                  if (next == null) return const SizedBox.shrink();
                  return Column(
                    children: [
                      _NextStepCard(next: next, onStart: c.openNextStep),
                      const SizedBox(height: 14),
                    ],
                  );
                }),
                _WorkspaceCard(service: c.workspaces),
                const SizedBox(height: 16),
                Text(
                  'العمل اليومي',
                  style: Theme.of(context).textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 8),
                ActionTile(
                  icon: Icons.folder_open,
                  title: 'مشاريعي',
                  subtitle: 'اختر مشروعاً واعرف ما يجب تنفيذه تالياً',
                  emphasized: true,
                  badge: 'ابدأ',
                  onTap: c.openProjects,
                ),
                ActionTile(
                  icon: Icons.auto_awesome,
                  title: 'الاستوديو الذكي',
                  subtitle: 'حوّل التوصيات إلى محتوى وصفحات وحملات',
                  onTap: () => Get.toNamed(Routes.studio),
                ),
                ActionTile(
                  icon: Icons.fact_check_outlined,
                  title: 'الموافقات',
                  subtitle: 'راجع المخرجات قبل اعتمادها أو إرسالها',
                  onTap: () => Get.toNamed(Routes.approvals),
                ),
                const SizedBox(height: 10),
                Text(
                  'الإدارة',
                  style: Theme.of(context).textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 8),
                ActionTile(
                  icon: Icons.group_outlined,
                  title: 'الفريق',
                  subtitle: 'الأعضاء والدعوات وتوزيع العمل',
                  onTap: () => Get.toNamed(Routes.team),
                ),
                Obx(() {
                  final isAgency = c.workspaces.active.value?.isAgency ?? false;
                  if (!isAgency) return const SizedBox.shrink();
                  return Column(
                    children: [
                      ActionTile(
                        icon: Icons.groups_outlined,
                        title: 'عملاء الوكالة',
                        subtitle: 'إدارة العملاء ومشاريعهم في مكان واحد',
                        onTap: () => Get.toNamed(Routes.clients),
                      ),
                      ActionTile(
                        icon: Icons.branding_watermark_outlined,
                        title: 'علامة الوكالة',
                        subtitle: 'العلامة البيضاء لتقاريرك ومخرجاتك',
                        onTap: () => Get.toNamed(Routes.agencyBranding),
                      ),
                    ],
                  );
                }),
                ActionTile(
                  icon: Icons.credit_card_outlined,
                  title: 'الفوترة والباقات',
                  subtitle: 'الباقة، الرصيد، والترقية',
                  onTap: () => Get.toNamed(Routes.billing),
                ),
                ActionTile(
                  icon: Icons.settings_outlined,
                  title: 'الحساب',
                  subtitle: 'إعداداتك وملفك التسويقي',
                  onTap: () => Get.toNamed(Routes.account),
                ),
              ],
            ),
          );
        }),
      ),
    );
  }
}

/// بطاقة الخطوة التالية: ماذا تفعل الآن بالضبط، ولماذا، وزر ينقلك إليها مباشرة.
class _NextStepCard extends StatelessWidget {
  const _NextStepCard({required this.next, required this.onStart});

  final Map<String, dynamic> next;
  final VoidCallback onStart;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final title = next['title']?.toString() ?? 'خطوتك التالية';
    final summary = next['summary']?.toString() ?? '';
    final details = (next['details'] is List)
        ? (next['details'] as List)
            .map((e) => e.toString())
            .where((s) => s.trim().isNotEmpty)
            .toList()
        : const <String>[];
    final actionLabel = next['action_label']?.toString() ?? 'ابدأ الآن';

    return Card(
      elevation: 0,
      color: theme.colorScheme.primaryContainer.withValues(alpha: 0.35),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Icon(Icons.flag_outlined, color: theme.colorScheme.primary),
                const SizedBox(width: 8),
                Text(
                  'الخطوة التالية',
                  style: theme.textTheme.labelLarge?.copyWith(
                    color: theme.colorScheme.primary,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 8),
            Text(
              title,
              style: theme.textTheme.titleMedium
                  ?.copyWith(fontWeight: FontWeight.w900),
            ),
            if (summary.isNotEmpty) ...[
              const SizedBox(height: 4),
              Text(summary,
                  style: theme.textTheme.bodyMedium?.copyWith(height: 1.6)),
            ],
            if (details.isNotEmpty) ...[
              const SizedBox(height: 10),
              for (final d in details)
                Padding(
                  padding: const EdgeInsets.only(bottom: 4),
                  child: Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Icon(Icons.check, size: 16,
                          color: theme.colorScheme.primary),
                      const SizedBox(width: 6),
                      Expanded(
                        child: Text(d,
                            style: theme.textTheme.bodySmall
                                ?.copyWith(height: 1.5)),
                      ),
                    ],
                  ),
                ),
            ],
            const SizedBox(height: 12),
            SizedBox(
              width: double.infinity,
              child: FilledButton.icon(
                onPressed: onStart,
                icon: const Icon(Icons.play_arrow_rounded),
                label: Text(actionLabel),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _WorkspaceCard extends StatelessWidget {
  const _WorkspaceCard({required this.service});

  final WorkspaceService service;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Obx(() {
      final active = service.active.value;
      return Card(
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Icon(
                    Icons.workspaces_outline,
                    color: theme.colorScheme.primary,
                  ),
                  const SizedBox(width: 8),
                  Text(
                    'مساحة العمل الحالية',
                    style: theme.textTheme.labelLarge?.copyWith(
                      color: theme.colorScheme.primary,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 8),
              Text(
                active?.name ?? '—',
                style: theme.textTheme.titleLarge?.copyWith(
                  fontWeight: FontWeight.w900,
                ),
              ),
              const SizedBox(height: 4),
              Text(
                'نوع الاستخدام: ${active?.type ?? '—'}',
                style: theme.textTheme.bodySmall,
              ),
              if (service.workspaces.length > 1) ...[
                const Divider(height: 24),
                DropdownButtonFormField<String>(
                  initialValue: active?.publicId,
                  decoration: const InputDecoration(labelText: 'تبديل المساحة'),
                  items: service.workspaces
                      .map(
                        (w) => DropdownMenuItem(
                          value: w.publicId,
                          child: Text(w.name),
                        ),
                      )
                      .toList(),
                  onChanged: (id) {
                    final ws = service.workspaces.firstWhereOrNull(
                      (w) => w.publicId == id,
                    );
                    if (ws != null) service.setActive(ws);
                  },
                ),
              ],
            ],
          ),
        ),
      );
    });
  }
}
