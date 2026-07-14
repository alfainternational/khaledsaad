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

  // إجراءات الحزمة الدلالية → حالة مستهدفة.
  static const _pkgActionStatus = <String, String>{
    'start_execution': 'in_progress',
    'mark_executed': 'executed',
    'start_measuring': 'measuring',
  };
  static const _pkgActionLabel = <String, String>{
    'start_execution': 'ابدأ التنفيذ',
    'mark_executed': 'وسمها منفّذة',
    'start_measuring': 'ابدأ القياس',
  };

  // إجراءات المهمة الدلالية → حالة مستهدفة.
  static const _taskActionStatus = <String, String>{
    'start': 'in_progress',
    'complete': 'done',
    'reopen': 'pending',
  };
  static const _taskActionLabel = <String, String>{
    'start': 'ابدأ',
    'complete': 'أنجز',
    'reopen': 'أعد فتحها',
  };

  static const _phaseLabels = <String, String>{
    'discovery': 'اكتشاف',
    'planning': 'تخطيط',
    'execution': 'تنفيذ',
    'validation': 'تحقق',
  };

  Future<void> _taskAction(String? taskPublicId, String action) async {
    final ws = _workspaces.activeId;
    final status = _taskActionStatus[action];
    if (ws == null || taskPublicId == null || status == null) return;
    try {
      _package.value = await _repo.updateTaskStatus(ws, taskPublicId, status);
      UiFeedback.success('تم تحديث المهمة.', title: 'حزمة التنفيذ');
    } on ApiException catch (e) {
      UiFeedback.error(e.message, title: 'حزمة التنفيذ');
    }
  }

  Future<void> _addReport() async {
    final ws = _workspaces.activeId;
    if (ws == null) return;
    final phase = 'execution'.obs;
    final progress = 50.0.obs;
    final note = TextEditingController();
    final metricName = TextEditingController();
    final metricValue = TextEditingController();

    final ok = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      builder: (sheetCtx) => Padding(
        padding: EdgeInsets.only(
          left: 20,
          right: 20,
          top: 20,
          bottom: MediaQuery.of(sheetCtx).viewInsets.bottom + 20,
        ),
        child: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text('تقرير قياس جديد',
                  style: Theme.of(sheetCtx).textTheme.titleMedium),
              const SizedBox(height: 16),
              Obx(() => DropdownButtonFormField<String>(
                    initialValue: phase.value,
                    decoration: const InputDecoration(
                        labelText: 'المرحلة', border: OutlineInputBorder()),
                    items: _phaseLabels.entries
                        .map((e) => DropdownMenuItem(
                            value: e.key, child: Text(e.value)))
                        .toList(),
                    onChanged: (v) {
                      if (v != null) phase.value = v;
                    },
                  )),
              const SizedBox(height: 16),
              Obx(() => Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text('التقدّم: ${progress.value.round()}%'),
                      Slider(
                        value: progress.value,
                        max: 100,
                        divisions: 20,
                        label: '${progress.value.round()}%',
                        onChanged: (v) => progress.value = v,
                      ),
                    ],
                  )),
              const SizedBox(height: 8),
              TextField(
                controller: note,
                maxLines: 2,
                decoration: const InputDecoration(
                    labelText: 'ملاحظة (اختياري)',
                    border: OutlineInputBorder()),
              ),
              const SizedBox(height: 12),
              Row(children: [
                Expanded(
                  child: TextField(
                    controller: metricName,
                    decoration: const InputDecoration(
                        labelText: 'اسم المؤشر', border: OutlineInputBorder()),
                  ),
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: TextField(
                    controller: metricValue,
                    decoration: const InputDecoration(
                        labelText: 'قيمته', border: OutlineInputBorder()),
                  ),
                ),
              ]),
              const SizedBox(height: 20),
              FilledButton(
                onPressed: () => Get.back(result: true),
                child: const Text('حفظ التقرير'),
              ),
            ],
          ),
        ),
      ),
    );

    if (ok == true) {
      try {
        _package.value = await _repo.addReport(
          ws,
          _packageId,
          phase: phase.value,
          progress: progress.value.round(),
          note: note.text,
          metricName: metricName.text,
          metricValue: metricValue.text,
        );
        UiFeedback.success('أُضيف تقرير القياس.', title: 'حزمة التنفيذ');
      } on ApiException catch (e) {
        UiFeedback.error(e.message, title: 'حزمة التنفيذ');
      }
    }
    note.dispose();
    metricName.dispose();
    metricValue.dispose();
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
            // ملخّص التقدّم + إجراءات الحزمة المتاحة
            Card(
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Chip(
                          visualDensity: VisualDensity.compact,
                          label: Text(pkg.statusLabel ??
                              ExecutionPackageModel.statusLabels[pkg.status] ??
                              pkg.status),
                        ),
                        const Spacer(),
                        if (pkg.deadline?.isNotEmpty ?? false)
                          Text('الموعد: ${pkg.deadline}',
                              style: theme.textTheme.bodySmall),
                      ],
                    ),
                    const SizedBox(height: 12),
                    ClipRRect(
                      borderRadius: BorderRadius.circular(6),
                      child: LinearProgressIndicator(
                        value: (pkg.progressPercent / 100).clamp(0.0, 1.0),
                        minHeight: 8,
                      ),
                    ),
                    const SizedBox(height: 6),
                    Text(
                      '${pkg.doneTasks} من ${pkg.totalTasks} مهمة (${pkg.progressPercent}%)'
                      '${pkg.owner != null ? ' · المسؤول: ${pkg.owner!['name'] ?? ''}' : ''}',
                      style: theme.textTheme.bodySmall,
                    ),
                    if (pkg.availableActions.any(_pkgActionStatus.containsKey)) ...[
                      const SizedBox(height: 12),
                      Wrap(
                        spacing: 8,
                        runSpacing: 8,
                        children: [
                          for (final a in pkg.availableActions)
                            if (_pkgActionStatus.containsKey(a))
                              FilledButton.tonal(
                                onPressed: () =>
                                    _updateStatus(_pkgActionStatus[a]!),
                                child: Text(_pkgActionLabel[a] ?? a),
                              ),
                        ],
                      ),
                    ],
                  ],
                ),
              ),
            ),
            const SizedBox(height: 8),
            // الحالة (تغيير يدوي)
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
              ...pkg.tasks.map((task) {
                final actions = (task['available_actions'] as List?)
                        ?.map((e) => e.toString())
                        .toList() ??
                    const <String>[];
                final tId = task['public_id']?.toString();
                final tStatusLabel = task['status_label']?.toString() ??
                    task['status']?.toString() ??
                    '';
                final done = task['status']?.toString() == 'done';
                return Card(
                  margin: const EdgeInsets.only(bottom: 6),
                  child: Padding(
                    padding: const EdgeInsets.all(12),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          children: [
                            Icon(
                              done
                                  ? Icons.check_circle
                                  : Icons.radio_button_unchecked,
                              size: 18,
                              color: done
                                  ? theme.colorScheme.primary
                                  : theme.colorScheme.outline,
                            ),
                            const SizedBox(width: 8),
                            Expanded(
                              child: Text(
                                task['title']?.toString() ?? '',
                                style: theme.textTheme.bodyMedium?.copyWith(
                                    fontWeight: FontWeight.w600),
                              ),
                            ),
                            Text(tStatusLabel,
                                style: theme.textTheme.labelSmall),
                          ],
                        ),
                        if (task['description']?.toString().isNotEmpty ??
                            false) ...[
                          const SizedBox(height: 4),
                          Text(task['description'].toString(),
                              style: theme.textTheme.bodySmall),
                        ],
                        if (actions.any(_taskActionStatus.containsKey)) ...[
                          const SizedBox(height: 8),
                          Wrap(
                            spacing: 8,
                            children: [
                              for (final a in actions)
                                if (_taskActionStatus.containsKey(a))
                                  OutlinedButton(
                                    onPressed: () => _taskAction(tId, a),
                                    child: Text(_taskActionLabel[a] ?? a),
                                  ),
                            ],
                          ),
                        ],
                      ],
                    ),
                  ),
                );
              }),
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
            // تقارير القياس (دائماً ظاهر — أضف/تابع القياس)
            const SizedBox(height: 12),
            Row(
              children: [
                Expanded(
                  child: Text('تقارير القياس',
                      style: theme.textTheme.titleMedium
                          ?.copyWith(fontWeight: FontWeight.w800)),
                ),
                TextButton.icon(
                  onPressed: _addReport,
                  icon: const Icon(Icons.add),
                  label: const Text('أضف'),
                ),
              ],
            ),
            const SizedBox(height: 6),
            if (pkg.reports.isEmpty)
              Text('لا تقارير قياس بعد.', style: theme.textTheme.bodySmall)
            else
              ...pkg.reports.map((r) {
                final phaseLbl = r['phase_label']?.toString() ??
                    _phaseLabels[r['phase']?.toString()] ??
                    r['phase']?.toString() ??
                    '';
                final prog =
                    (r['progress'] is num) ? (r['progress'] as num).toInt() : 0;
                return Card(
                  margin: const EdgeInsets.only(bottom: 6),
                  child: ListTile(
                    dense: true,
                    leading: const Icon(Icons.timeline_outlined),
                    title: Text('$phaseLbl · $prog%'),
                    subtitle: (r['created_at']?.toString().isNotEmpty ?? false)
                        ? Text(r['created_at'].toString())
                        : null,
                  ),
                );
              }),
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
