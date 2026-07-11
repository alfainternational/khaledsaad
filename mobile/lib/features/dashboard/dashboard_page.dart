import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../app/routes/app_routes.dart';
import '../../data/repositories/auth_repository.dart';
import '../../data/repositories/collab_repository.dart';
import '../../data/services/session_service.dart';
import '../../data/services/workspace_service.dart';
import '../shared/widgets/app_state_view.dart';
import 'dashboard_controller.dart';

class DashboardPage extends StatelessWidget {
  const DashboardPage({super.key});

  @override
  Widget build(BuildContext context) {
    final c = Get.put(DashboardController(
      Get.find<WorkspaceService>(),
      Get.find<AuthRepository>(),
      Get.find<SessionService>(),
      Get.find<CollabRepository>(),
    ));

    return Scaffold(
      appBar: AppBar(
        title: const Text('لوحة المتابعة'),
        actions: [
          IconButton(
            tooltip: 'تسجيل الخروج',
            icon: const Icon(Icons.logout),
            onPressed: c.logout,
          ),
        ],
      ),
      body: Obx(() {
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
            padding: const EdgeInsets.all(16),
            children: [
              Obx(() {
                if (!c.workspaces.isOffline.value) return const SizedBox.shrink();
                return Container(
                  margin: const EdgeInsets.only(bottom: 12),
                  padding: const EdgeInsets.all(10),
                  decoration: BoxDecoration(
                    color: Theme.of(context).colorScheme.tertiaryContainer,
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: Row(
                    children: [
                      const Icon(Icons.wifi_off, size: 18),
                      const SizedBox(width: 8),
                      Expanded(
                        child: Text('أنت بلا اتصال — تُعرض آخر نسخة محفوظة.',
                            style: Theme.of(context).textTheme.bodySmall),
                      ),
                    ],
                  ),
                );
              }),
              _WorkspaceCard(service: c.workspaces),
              const SizedBox(height: 16),
              Card(
                child: ListTile(
                  leading: const Icon(Icons.folder_open),
                  title: const Text('مشاريعي'),
                  subtitle: const Text('إدارة مشاريعك وتشغيل الأدوات'),
                  trailing: const Icon(Icons.chevron_left),
                  onTap: c.openProjects,
                ),
              ),
              const SizedBox(height: 8),
              Card(
                child: ListTile(
                  leading: const Icon(Icons.auto_awesome),
                  title: const Text('الاستوديو الذكي'),
                  subtitle: const Text('توليد محتوى تسويقي جاهز'),
                  trailing: const Icon(Icons.chevron_left),
                  onTap: () => Get.toNamed(Routes.studio),
                ),
              ),
              const SizedBox(height: 8),
              Card(
                child: ListTile(
                  leading: const Icon(Icons.group_outlined),
                  title: const Text('الفريق'),
                  subtitle: const Text('الأعضاء والدعوات'),
                  trailing: const Icon(Icons.chevron_left),
                  onTap: () => Get.toNamed(Routes.team),
                ),
              ),
              const SizedBox(height: 8),
              Card(
                child: ListTile(
                  leading: const Icon(Icons.fact_check_outlined),
                  title: const Text('الموافقات'),
                  subtitle: const Text('مراجعة واعتماد المخرجات'),
                  trailing: const Icon(Icons.chevron_left),
                  onTap: () => Get.toNamed(Routes.approvals),
                ),
              ),
              Obx(() {
                final isAgency = c.workspaces.active.value?.isAgency ?? false;
                if (!isAgency) return const SizedBox.shrink();
                return Column(
                  children: [
                    const SizedBox(height: 8),
                    Card(
                      child: ListTile(
                        leading: const Icon(Icons.groups_outlined),
                        title: const Text('عملاء الوكالة'),
                        subtitle: const Text('إدارة عملائك ومشاريعهم'),
                        trailing: const Icon(Icons.chevron_left),
                        onTap: () => Get.toNamed(Routes.clients),
                      ),
                    ),
                    const SizedBox(height: 8),
                    Card(
                      child: ListTile(
                        leading: const Icon(Icons.branding_watermark_outlined),
                        title: const Text('علامة الوكالة'),
                        subtitle: const Text('العلامة البيضاء لتقاريرك'),
                        trailing: const Icon(Icons.chevron_left),
                        onTap: () => Get.toNamed(Routes.agencyBranding),
                      ),
                    ),
                  ],
                );
              }),
              const SizedBox(height: 8),
              Card(
                child: ListTile(
                  leading: const Icon(Icons.credit_card_outlined),
                  title: const Text('الفوترة والباقات'),
                  subtitle: const Text('باقتك ورصيدك والترقية'),
                  trailing: const Icon(Icons.chevron_left),
                  onTap: () => Get.toNamed(Routes.billing),
                ),
              ),
              const SizedBox(height: 8),
              Card(
                child: ListTile(
                  leading: const Icon(Icons.settings_outlined),
                  title: const Text('الحساب'),
                  subtitle: const Text('إعداداتك وملفك التسويقي'),
                  trailing: const Icon(Icons.chevron_left),
                  onTap: () => Get.toNamed(Routes.account),
                ),
              ),
            ],
          ),
        );
      }),
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
              Text('مساحة العمل الحالية',
                  style: theme.textTheme.labelMedium
                      ?.copyWith(color: theme.colorScheme.primary)),
              const SizedBox(height: 6),
              Text(active?.name ?? '—',
                  style: theme.textTheme.titleLarge
                      ?.copyWith(fontWeight: FontWeight.w800)),
              const SizedBox(height: 4),
              Text('النوع: ${active?.type ?? '—'}',
                  style: theme.textTheme.bodySmall),
              if (service.workspaces.length > 1) ...[
                const Divider(height: 24),
                DropdownButtonFormField<String>(
                  initialValue: active?.publicId,
                  decoration: const InputDecoration(labelText: 'تبديل المساحة'),
                  items: service.workspaces
                      .map((w) => DropdownMenuItem(
                            value: w.publicId,
                            child: Text(w.name),
                          ))
                      .toList(),
                  onChanged: (id) {
                    final ws = service.workspaces
                        .firstWhereOrNull((w) => w.publicId == id);
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
