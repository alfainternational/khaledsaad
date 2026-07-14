import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:get/get.dart';

import '../../app/routes/app_routes.dart';
import '../../data/repositories/auth_repository.dart';
import '../../data/repositories/collab_repository.dart';
import '../../data/services/session_service.dart';
import '../../data/services/workspace_service.dart';
import '../shell/home_shell.dart';
import '../shared/widgets/action_tile.dart';
import '../shared/widgets/animated_app_background.dart';
import '../shared/widgets/app_state_view.dart';
import '../shared/widgets/hero_panel.dart';
import 'dashboard_controller.dart';

class DashboardPage extends StatelessWidget {
  const DashboardPage({super.key});

  // اختصارات اللوحة تبدّل تبويب الهيكل السفلي إن وُجد، وإلا تفتح المسار.
  void _openStudio() {
    if (Get.isRegistered<HomeShellController>()) {
      Get.find<HomeShellController>().go(2);
    } else {
      Get.toNamed(Routes.studio);
    }
  }

  void _openAccount() {
    if (Get.isRegistered<HomeShellController>()) {
      Get.find<HomeShellController>().go(3);
    } else {
      Get.toNamed(Routes.account);
    }
  }

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
            tooltip: 'بحث',
            icon: const Icon(Icons.search),
            onPressed: () => Get.toNamed(Routes.search),
          ),
          IconButton(
            tooltip: 'الإشعارات',
            icon: const Icon(Icons.notifications_outlined),
            onPressed: () => Get.toNamed(Routes.notifications),
          ),
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
            return AppStateView.skeleton();
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
                _GuideHeader(controller: c),
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
                _RecommendationsCard(items: c.recommendations),
                _WorkspaceCard(service: c.workspaces),
                _ActivityCard(items: c.recentActivity),
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
                  onTap: () {
                    HapticFeedback.selectionClick();
                    c.openProjects();
                  },
                ),
                ActionTile(
                  icon: Icons.auto_awesome,
                  title: 'الاستوديو الذكي',
                  subtitle: 'حوّل التوصيات إلى محتوى وصفحات وحملات',
                  onTap: () => _openStudio(),
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
                  onTap: () => _openAccount(),
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
                onPressed: () {
                  HapticFeedback.selectionClick();
                  onStart();
                },
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

/// رأس المرشد التنفيذي: تحية + المرحلة الحالية + نسبة إكمال المسار.
/// كل قسم محروس بفراغه، فلا يظهر شيء إن لم تتوفّر بياناته.
class _GuideHeader extends StatelessWidget {
  const _GuideHeader({required this.controller});

  final DashboardController controller;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final name = controller.userName.value?.trim();
    final stage = controller.currentStage;
    final stageLabel = controller.currentStageLabel;
    final projectName = controller.currentProjectName?.trim();
    final percent = controller.pathCompletionPercent;

    final hasGreeting = name != null && name.isNotEmpty;
    final hasStage = stage != null || (stageLabel != null && stageLabel.isNotEmpty);
    final hasPercent = percent != null;

    if (!hasGreeting && !hasStage && !hasPercent) {
      return const SizedBox.shrink();
    }

    final stageText = stageLabel != null && stageLabel.isNotEmpty
        ? (stage != null ? 'المرحلة $stage — $stageLabel' : stageLabel)
        : (stage != null ? 'المرحلة $stage' : null);

    return Padding(
      padding: const EdgeInsets.only(bottom: 14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          if (hasGreeting)
            Semantics(
              header: true,
              child: Text(
                'مرحباً، $name',
                style: theme.textTheme.titleLarge
                    ?.copyWith(fontWeight: FontWeight.w900),
              ),
            ),
          if (projectName != null && projectName.isNotEmpty) ...[
            const SizedBox(height: 2),
            Text(
              'مشروعك الحالي: $projectName',
              style: theme.textTheme.bodySmall,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
            ),
          ],
          if (stageText != null) ...[
            const SizedBox(height: 8),
            Row(
              children: [
                Semantics(
                  label: 'المرحلة الحالية',
                  child: Icon(Icons.flag_outlined,
                      size: 18, color: theme.colorScheme.primary),
                ),
                const SizedBox(width: 6),
                Expanded(
                  child: Text(
                    stageText,
                    style: theme.textTheme.bodyMedium
                        ?.copyWith(fontWeight: FontWeight.w700),
                  ),
                ),
              ],
            ),
          ],
          if (hasPercent) ...[
            const SizedBox(height: 10),
            Row(
              children: [
                Text('إكمال المسار',
                    style: theme.textTheme.labelMedium),
                const Spacer(),
                Text('$percent%',
                    style: theme.textTheme.labelMedium?.copyWith(
                        fontWeight: FontWeight.w800,
                        color: theme.colorScheme.primary)),
              ],
            ),
            const SizedBox(height: 6),
            Semantics(
              label: 'نسبة إكمال المسار',
              value: '$percent بالمئة',
              child: ClipRRect(
                borderRadius: BorderRadius.circular(6),
                child: LinearProgressIndicator(
                  value: (percent / 100).clamp(0.0, 1.0),
                  minHeight: 8,
                  backgroundColor: theme.colorScheme.surfaceContainerHighest,
                ),
              ),
            ),
          ],
        ],
      ),
    );
  }
}

/// توصيات تنفيذية مختصرة — تظهر فقط عند توفّرها.
class _RecommendationsCard extends StatelessWidget {
  const _RecommendationsCard({required this.items});

  final List<String> items;

  @override
  Widget build(BuildContext context) {
    if (items.isEmpty) return const SizedBox.shrink();
    final theme = Theme.of(context);
    return Padding(
      padding: const EdgeInsets.only(bottom: 14),
      child: Card(
        elevation: 0,
        color: theme.colorScheme.secondaryContainer.withValues(alpha: 0.35),
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Icon(Icons.tips_and_updates_outlined,
                      color: theme.colorScheme.primary),
                  const SizedBox(width: 8),
                  Text(
                    'توصيات لتقدّمك',
                    style: theme.textTheme.labelLarge?.copyWith(
                      color: theme.colorScheme.primary,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 8),
              for (final item in items)
                Padding(
                  padding: const EdgeInsets.only(bottom: 6),
                  child: Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Icon(Icons.arrow_left,
                          size: 18, color: theme.colorScheme.primary),
                      Expanded(
                        child: Text(item,
                            style: theme.textTheme.bodyMedium
                                ?.copyWith(height: 1.5)),
                      ),
                    ],
                  ),
                ),
            ],
          ),
        ),
      ),
    );
  }
}

/// آخر النشاط — تشغيلات الأدوات الأخيرة. يظهر فقط عند وجود نشاط.
class _ActivityCard extends StatelessWidget {
  const _ActivityCard({required this.items});

  final List<({String title, String subtitle})> items;

  @override
  Widget build(BuildContext context) {
    if (items.isEmpty) return const SizedBox.shrink();
    final theme = Theme.of(context);
    return Padding(
      padding: const EdgeInsets.only(top: 14),
      child: Card(
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Icon(Icons.history, color: theme.colorScheme.primary),
                  const SizedBox(width: 8),
                  Text(
                    'آخر النشاط',
                    style: theme.textTheme.labelLarge?.copyWith(
                      color: theme.colorScheme.primary,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 6),
              for (final item in items)
                Padding(
                  padding: const EdgeInsets.symmetric(vertical: 6),
                  child: Row(
                    children: [
                      Icon(Icons.check_circle_outline,
                          size: 18,
                          color: theme.colorScheme.onSurfaceVariant),
                      const SizedBox(width: 8),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(item.title,
                                style: theme.textTheme.bodyMedium?.copyWith(
                                    fontWeight: FontWeight.w600),
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis),
                            if (item.subtitle.isNotEmpty)
                              Text(item.subtitle,
                                  style: theme.textTheme.bodySmall,
                                  maxLines: 1,
                                  overflow: TextOverflow.ellipsis),
                          ],
                        ),
                      ),
                    ],
                  ),
                ),
            ],
          ),
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
