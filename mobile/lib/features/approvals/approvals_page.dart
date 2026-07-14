import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../app/theme/app_semantic_colors.dart';
import '../../core/error/api_exception.dart';
import '../../core/l10n/ar_labels.dart';
import '../../data/models/collab_models.dart';
import '../../data/repositories/collab_repository.dart';
import '../../data/services/workspace_service.dart';
import '../shared/widgets/app_state_view.dart';
import '../shared/widgets/status_badge.dart';
import '../shared/widgets/ui_feedback.dart';

/// الموافقات: عدّادات + فلترة + مراجعة (اعتماد/رفض) — بدون زحمة.
class ApprovalsPage extends StatefulWidget {
  const ApprovalsPage({super.key});

  @override
  State<ApprovalsPage> createState() => _ApprovalsPageState();
}

class _ApprovalsPageState extends State<ApprovalsPage> {
  late final CollabRepository _repo = Get.find<CollabRepository>();
  late final WorkspaceService _workspaces = Get.find<WorkspaceService>();

  final _approvals = <ApprovalModel>[].obs;
  final _meta = Rxn<Map<String, dynamic>>();
  final _statusFilter = RxnString();
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
      final result = await _repo.approvals(ws, status: _statusFilter.value);
      _approvals.assignAll(result.approvals);
      _meta.value = result.meta;
    } on ApiException catch (e) {
      _error.value = e.message;
    } finally {
      _loading.value = false;
    }
  }

  Future<void> _review(ApprovalModel approval, String status) async {
    final ws = _workspaces.activeId;
    if (ws == null) return;
    try {
      await _repo.reviewApproval(ws, approval.id, status: status);
      UiFeedback.success(status == 'approved' ? 'تم الاعتماد.' : 'تم الرفض.');
      await _load();
    } on ApiException catch (e) {
      UiFeedback.error(e.message);
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Scaffold(
      appBar: AppBar(title: const Text('الموافقات')),
      body: Obx(() {
        if (_loading.value && _approvals.isEmpty && _meta.value == null) {
          return AppStateView.loading();
        }
        if (_error.value != null && _approvals.isEmpty) {
          return AppStateView.error(message: _error.value, onRetry: _load);
        }
        final meta = _meta.value ?? {};
        final sem = AppSemanticColors.of(context);
        return RefreshIndicator(
          onRefresh: _load,
          child: ListView(
            padding: const EdgeInsets.all(16),
            children: [
              // عدّادات هادئة
              Row(
                children: [
                  _CountChip(
                    label: 'معلّقة',
                    count: (meta['pending_count'] as num?)?.toInt() ?? 0,
                    color: sem.warning,
                    selected: _statusFilter.value == 'pending',
                    onTap: () => _setFilter('pending'),
                  ),
                  const SizedBox(width: 8),
                  _CountChip(
                    label: 'معتمدة',
                    count: (meta['approved_count'] as num?)?.toInt() ?? 0,
                    color: sem.success,
                    selected: _statusFilter.value == 'approved',
                    onTap: () => _setFilter('approved'),
                  ),
                  const SizedBox(width: 8),
                  _CountChip(
                    label: 'مرفوضة',
                    count: (meta['rejected_count'] as num?)?.toInt() ?? 0,
                    color: sem.danger,
                    selected: _statusFilter.value == 'rejected',
                    onTap: () => _setFilter('rejected'),
                  ),
                ],
              ),
              const SizedBox(height: 16),
              if (_approvals.isEmpty)
                Padding(
                  padding: const EdgeInsets.symmetric(vertical: 32),
                  child: Column(
                    children: [
                      Icon(Icons.fact_check_outlined,
                          size: 40,
                          color:
                              theme.colorScheme.primary.withValues(alpha: 0.6)),
                      const SizedBox(height: 12),
                      Text(
                        'لا توجد موافقات هنا بعد.',
                        style: theme.textTheme.titleSmall
                            ?.copyWith(fontWeight: FontWeight.w700),
                        textAlign: TextAlign.center,
                      ),
                      const SizedBox(height: 6),
                      Text(
                        'كل مخرج جديد من الاستوديو الذكي وكل حزمة تنفيذ تدخل هذا الطابور تلقائياً لتراجعها وتعتمدها قبل استخدامها. ولّد مخرجاً من الاستوديو أو حوّل توصية إلى حزمة تنفيذ وستجده هنا.',
                        style: theme.textTheme.bodyMedium?.copyWith(height: 1.7),
                        textAlign: TextAlign.center,
                      ),
                    ],
                  ),
                )
              else
                ..._approvals.map((a) => _ApprovalCard(
                      approval: a,
                      onApprove: () => _review(a, 'approved'),
                      onReject: () => _review(a, 'rejected'),
                    )),
            ],
          ),
        );
      }),
    );
  }

  void _setFilter(String status) {
    _statusFilter.value = _statusFilter.value == status ? null : status;
    _load();
  }
}

class _CountChip extends StatelessWidget {
  const _CountChip({
    required this.label,
    required this.count,
    required this.color,
    required this.selected,
    required this.onTap,
  });

  final String label;
  final int count;
  final Color color;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Expanded(
      child: InkWell(
        borderRadius: BorderRadius.circular(12),
        onTap: onTap,
        child: Container(
          padding: const EdgeInsets.symmetric(vertical: 10),
          decoration: BoxDecoration(
            color: color.withValues(alpha: selected ? 0.22 : 0.10),
            borderRadius: BorderRadius.circular(12),
            border: selected ? Border.all(color: color, width: 1.4) : null,
          ),
          child: Column(
            children: [
              Text('$count',
                  style: TextStyle(
                      color: color, fontWeight: FontWeight.w800, fontSize: 18)),
              Text(label, style: TextStyle(color: color, fontSize: 12)),
            ],
          ),
        ),
      ),
    );
  }
}

class _ApprovalCard extends StatefulWidget {
  const _ApprovalCard({
    required this.approval,
    required this.onApprove,
    required this.onReject,
  });

  final ApprovalModel approval;
  final Future<void> Function() onApprove;
  final Future<void> Function() onReject;

  @override
  State<_ApprovalCard> createState() => _ApprovalCardState();
}

class _ApprovalCardState extends State<_ApprovalCard> {
  bool _busy = false;

  /// يمنع النقر المزدوج: يعطّل الأزرار ويعرض مؤشراً أثناء المراجعة.
  Future<void> _run(Future<void> Function() action) async {
    if (_busy) return;
    setState(() => _busy = true);
    try {
      await action();
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final approval = widget.approval;
    return Card(
      margin: const EdgeInsets.only(bottom: 10),
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(
                  child: Text(
                    ApprovalModel.itemTypeLabels[approval.itemType] ??
                        ArLabels.of(approval.itemType),
                    style: theme.textTheme.titleSmall
                        ?.copyWith(fontWeight: FontWeight.w700),
                  ),
                ),
                StatusBadge(status: approval.status),
              ],
            ),
            if (approval.projectName != null) ...[
              const SizedBox(height: 4),
              Text('المشروع: ${approval.projectName}',
                  style: theme.textTheme.bodySmall),
            ],
            if (approval.note?.isNotEmpty ?? false) ...[
              const SizedBox(height: 6),
              Text(approval.note!, style: theme.textTheme.bodyMedium),
            ],
            if (approval.status == 'pending') ...[
              const SizedBox(height: 12),
              Row(
                children: [
                  Expanded(
                    child: FilledButton.tonal(
                      onPressed: _busy ? null : () => _run(widget.onApprove),
                      child: _busy
                          ? const SizedBox(
                              height: 16,
                              width: 16,
                              child:
                                  CircularProgressIndicator(strokeWidth: 2))
                          : const Text('اعتماد'),
                    ),
                  ),
                  const SizedBox(width: 8),
                  Expanded(
                    child: OutlinedButton(
                      onPressed: _busy ? null : () => _run(widget.onReject),
                      child: const Text('رفض'),
                    ),
                  ),
                ],
              ),
            ],
          ],
        ),
      ),
    );
  }
}
