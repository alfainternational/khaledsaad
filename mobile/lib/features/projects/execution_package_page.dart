import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../app/routes/app_routes.dart';
import '../../core/error/api_exception.dart';
import '../../data/models/lifecycle_models.dart';
import '../../data/repositories/lifecycle_repository.dart';
import '../../data/services/workspace_service.dart';
import '../shared/widgets/app_state_view.dart';
import '../shared/widgets/ui_feedback.dart';

/// حزمة التنفيذ: القرار والمهام والأصول + تغيير الحالة.
class ExecutionPackagePage extends StatefulWidget {
  const ExecutionPackagePage({super.key});

  @override
  State<ExecutionPackagePage> createState() => _ExecutionPackagePageState();
}

class _ExecutionPackagePageState extends State<ExecutionPackagePage> {
  late final String _packageId = Get.arguments as String;
  late final LifecycleRepository _repo = Get.find<LifecycleRepository>();
  late final WorkspaceService _workspaces = Get.find<WorkspaceService>();

  final _package = Rxn<ExecutionPackageModel>();
  final _loading = true.obs;
  final _error = RxnString();

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    final ws = _workspaces.activeId;
    if (ws == null) {
      _loading.value = false;
      _error.value = 'لا توجد مساحة عمل نشطة.';
      return;
    }
    _loading.value = true;
    _error.value = null;
    try {
      _package.value = await _repo.package(ws, _packageId);
    } on ApiException catch (e) {
      _error.value = e.message;
    } finally {
      _loading.value = false;
    }
  }

  Future<void> _updateStatus(String status) async {
    final ws = _workspaces.activeId;
    if (ws == null) return;
    try {
      _package.value = await _repo.updatePackageStatus(ws, _packageId, status);
      UiFeedback.success('تم تحديث الحالة.', title: 'حزمة التنفيذ');
    } on ApiException catch (e) {
      UiFeedback.error(e.message, title: 'حزمة التنفيذ');
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Scaffold(
      appBar: AppBar(title: const Text('حزمة التنفيذ')),
      body: Obx(() {
        if (_loading.value) return AppStateView.loading();
        if (_error.value != null) {
          return AppStateView.error(message: _error.value, onRetry: _load);
        }
        final pkg = _package.value;
        if (pkg == null) {
          return AppStateView.empty(
            icon: Icons.inventory_2_outlined,
            title: 'الحزمة غير متاحة',
          );
        }
        return RefreshIndicator(
          onRefresh: _load,
          child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            Text(
              pkg.title,
              style: theme.textTheme.titleLarge?.copyWith(
                fontWeight: FontWeight.w800,
              ),
            ),
            const SizedBox(height: 12),
            // الحالة
            Card(
              child: Padding(
                padding: const EdgeInsets.symmetric(
                  horizontal: 16,
                  vertical: 8,
                ),
                child: Row(
                  children: [
                    Text('الحالة:', style: theme.textTheme.bodyMedium),
                    const SizedBox(width: 12),
                    Expanded(
                      child: DropdownButtonHideUnderline(
                        child: DropdownButton<String>(
                          value: pkg.status,
                          isExpanded: true,
                          items: ExecutionPackageModel.statuses
                              .map(
                                (s) => DropdownMenuItem(
                                  value: s,
                                  child: Text(
                                    ExecutionPackageModel.statusLabels[s] ?? s,
                                  ),
                                ),
                              )
                              .toList(),
                          onChanged: (s) {
                            if (s != null && s != pkg.status) _updateStatus(s);
                          },
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 8),
            if (pkg.problem?.isNotEmpty ?? false)
              _Section(title: 'المشكلة', body: pkg.problem!),
            if (pkg.evidence?.isNotEmpty ?? false)
              _Section(title: 'الدليل', body: pkg.evidence!),
            if (pkg.decision?.isNotEmpty ?? false)
              _Section(title: 'القرار', body: pkg.decision!),
            if (pkg.measurementPlan?.isNotEmpty ?? false)
              _Section(title: 'خطة القياس', body: pkg.measurementPlan!),
            const SizedBox(height: 4),
            Card(
              child: ListTile(
                leading: Icon(
                  Icons.auto_awesome,
                  color: theme.colorScheme.primary,
                ),
                title: const Text('تسليم Studio من هذه الحزمة'),
                subtitle: const Text(
                  'استخدم المشكلة والقرار والأصول كموجز جاهز لتوليد صفحة، إعلان، رسالة أو محتوى.',
                ),
                trailing: const Icon(Icons.chevron_left),
                onTap: () => Get.toNamed(
                  Routes.studio,
                  arguments: {
                    'brief': pkg.studioBrief,
                    'source_title': pkg.title,
                  },
                ),
              ),
            ),
            if (pkg.tasks.isNotEmpty) ...[
              const SizedBox(height: 12),
              Text(
                'المهام',
                style: theme.textTheme.titleMedium?.copyWith(
                  fontWeight: FontWeight.w800,
                ),
              ),
              const SizedBox(height: 6),
              ...pkg.tasks.map(
                (task) => Card(
                  margin: const EdgeInsets.only(bottom: 6),
                  child: ListTile(
                    dense: true,
                    leading: const Icon(Icons.check_circle_outline),
                    title: Text(task['title']?.toString() ?? ''),
                    subtitle:
                        (task['description']?.toString().isNotEmpty ?? false)
                        ? Text(task['description'].toString())
                        : null,
                  ),
                ),
              ),
            ],
            if (pkg.assets.isNotEmpty) ...[
              const SizedBox(height: 12),
              Text(
                'الأصول الجاهزة',
                style: theme.textTheme.titleMedium?.copyWith(
                  fontWeight: FontWeight.w800,
                ),
              ),
              const SizedBox(height: 6),
              ...pkg.assets.map(
                (asset) => Card(
                  margin: const EdgeInsets.only(bottom: 6),
                  clipBehavior: Clip.antiAlias,
                  child: ExpansionTile(
                    leading: const Icon(Icons.attachment_outlined),
                    title: Text(asset['title']?.toString() ?? ''),
                    childrenPadding: const EdgeInsets.all(16),
                    children: [SelectableText(asset['body']?.toString() ?? '')],
                  ),
                ),
              ),
            ],
          ],
          ),
        );
      }),
    );
  }
}

class _Section extends StatelessWidget {
  const _Section({required this.title, required this.body});

  final String title;
  final String body;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Card(
      margin: const EdgeInsets.only(bottom: 8),
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              title,
              style: theme.textTheme.labelLarge?.copyWith(
                color: theme.colorScheme.primary,
              ),
            ),
            const SizedBox(height: 4),
            Text(
              body,
              style: theme.textTheme.bodyMedium?.copyWith(height: 1.6),
            ),
          ],
        ),
      ),
    );
  }
}
