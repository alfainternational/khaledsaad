import 'dart:async';

import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../app/routes/app_routes.dart';
import '../../core/error/api_exception.dart';
import '../../data/models/lifecycle_models.dart';
import '../../data/repositories/lifecycle_repository.dart';
import '../../data/services/workspace_service.dart';
import '../shared/widgets/app_state_view.dart';

class IntelligenceController extends GetxController {
  IntelligenceController(this._repo, this._workspaces, this.projectId);

  final LifecycleRepository _repo;
  final WorkspaceService _workspaces;
  final String projectId;

  final recommendations = <RecommendationModel>[].obs;
  final auditInProgress = false.obs;
  final auditStatus = RxnString();
  final isLoading = false.obs;
  final error = RxnString();

  Timer? _pollTimer;

  @override
  void onReady() {
    super.onReady();
    load();
  }

  @override
  void onClose() {
    _pollTimer?.cancel();
    super.onClose();
  }

  Future<void> load() async {
    final ws = _workspaces.activeId;
    if (ws == null) return;
    isLoading.value = true;
    error.value = null;
    try {
      final results = await Future.wait([
        _repo.auditStatus(ws, projectId),
        _repo.recommendations(ws, projectId),
      ]);
      _applyStatus(results[0] as Map<String, dynamic>);
      recommendations
          .assignAll(results[1] as List<RecommendationModel>);
    } on ApiException catch (e) {
      error.value = e.message;
    } finally {
      isLoading.value = false;
    }
  }

  void _applyStatus(Map<String, dynamic> status) {
    auditStatus.value = status['status']?.toString();
    final inProgress = status['in_progress'] == true;
    auditInProgress.value = inProgress;
    if (inProgress) {
      _startPolling();
    } else {
      _pollTimer?.cancel();
    }
  }

  void _startPolling() {
    _pollTimer?.cancel();
    _pollTimer = Timer.periodic(const Duration(seconds: 6), (_) async {
      final ws = _workspaces.activeId;
      if (ws == null) return;
      try {
        final status = await _repo.auditStatus(ws, projectId);
        final wasInProgress = auditInProgress.value;
        _applyStatus(status);
        // اكتمل التحليل → حدّث التوصيات تلقائياً.
        if (wasInProgress && !auditInProgress.value) {
          recommendations.assignAll(await _repo.recommendations(ws, projectId));
        }
      } on ApiException catch (_) {
        // نتجاهل أخطاء الـ polling العابرة.
      }
    });
  }

  Future<void> runAudit() async {
    final ws = _workspaces.activeId;
    if (ws == null || auditInProgress.value) return;
    try {
      final result = await _repo.runAudit(ws, projectId);
      auditInProgress.value = true;
      auditStatus.value = result['status']?.toString() ?? 'queued';
      _startPolling();
      Get.snackbar('التحليل الذكي',
          result['message']?.toString() ?? 'تمت جدولة التحليل.',
          snackPosition: SnackPosition.BOTTOM);
    } on ApiException catch (e) {
      Get.snackbar('التحليل الذكي', e.message,
          snackPosition: SnackPosition.BOTTOM);
    }
  }

  Future<void> openPackage(RecommendationModel rec) async {
    final ws = _workspaces.activeId;
    if (ws == null) return;
    try {
      // idempotent: يعيد الحزمة الموجودة أو ينشئها.
      final pkg = rec.packagePublicId != null
          ? await _repo.package(ws, rec.packagePublicId!)
          : await _repo.createPackage(ws, projectId, rec.publicId);
      Get.toNamed(Routes.executionPackage, arguments: pkg.publicId);
    } on ApiException catch (e) {
      Get.snackbar('حزمة التنفيذ', e.message,
          snackPosition: SnackPosition.BOTTOM);
    }
  }
}

/// شاشة التحليل الذكي والتوصيات — قسمان هادئان: حالة التحليل ثم التوصيات.
class IntelligencePage extends StatelessWidget {
  const IntelligencePage({super.key});

  @override
  Widget build(BuildContext context) {
    final projectId = Get.arguments as String;
    final c = Get.put(
      IntelligenceController(
        Get.find<LifecycleRepository>(),
        Get.find<WorkspaceService>(),
        projectId,
      ),
      tag: projectId,
    );
    final theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(title: const Text('التحليل والتوصيات')),
      body: Obx(() {
        if (c.isLoading.value && c.recommendations.isEmpty) {
          return AppStateView.loading();
        }
        if (c.error.value != null && c.recommendations.isEmpty) {
          return AppStateView.error(message: c.error.value, onRetry: c.load);
        }
        return RefreshIndicator(
          onRefresh: c.load,
          child: ListView(
            padding: const EdgeInsets.all(16),
            children: [
              // بطاقة حالة التحليل
              Card(
                child: Padding(
                  padding: const EdgeInsets.all(16),
                  child: Obx(() {
                    final inProgress = c.auditInProgress.value;
                    return Row(
                      children: [
                        inProgress
                            ? const SizedBox(
                                height: 22,
                                width: 22,
                                child:
                                    CircularProgressIndicator(strokeWidth: 2.4))
                            : Icon(Icons.query_stats,
                                color: theme.colorScheme.primary),
                        const SizedBox(width: 12),
                        Expanded(
                          child: Text(
                            inProgress
                                ? 'التحليل قيد التنفيذ... سيكتمل تلقائياً.'
                                : 'حلّل حضور مشروعك التسويقي واحصل على توصيات.',
                            style: theme.textTheme.bodyMedium,
                          ),
                        ),
                        if (!inProgress)
                          FilledButton.tonal(
                            onPressed: c.runAudit,
                            child: const Text('حلّل الآن'),
                          ),
                      ],
                    );
                  }),
                ),
              ),
              const SizedBox(height: 20),
              Text('التوصيات',
                  style: theme.textTheme.titleMedium
                      ?.copyWith(fontWeight: FontWeight.w800)),
              const SizedBox(height: 8),
              if (c.recommendations.isEmpty)
                Padding(
                  padding: const EdgeInsets.symmetric(vertical: 20),
                  child: Text(
                    'لا توجد توصيات بعد. شغّل التحليل أولاً وستظهر هنا.',
                    style: theme.textTheme.bodyMedium,
                    textAlign: TextAlign.center,
                  ),
                )
              else
                ...c.recommendations.map((rec) => _RecommendationCard(
                      rec: rec,
                      onPackage: () => c.openPackage(rec),
                    )),
            ],
          ),
        );
      }),
    );
  }
}

class _RecommendationCard extends StatelessWidget {
  const _RecommendationCard({required this.rec, required this.onPackage});

  final RecommendationModel rec;
  final VoidCallback onPackage;

  Color _severityColor(BuildContext context) => switch (rec.severity) {
        'high' || 'critical' => const Color(0xFFDC2626),
        'medium' => const Color(0xFFD97706),
        _ => const Color(0xFF16A34A),
      };

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Card(
      margin: const EdgeInsets.only(bottom: 10),
      clipBehavior: Clip.antiAlias,
      child: ExpansionTile(
        leading: Icon(Icons.tips_and_updates_outlined,
            color: _severityColor(context)),
        title: Text(rec.title,
            style:
                theme.textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w700)),
        subtitle: rec.area != null ? Text(rec.area!) : null,
        childrenPadding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
        children: [
          if (rec.rationale != null && rec.rationale!.isNotEmpty)
            _Detail(label: 'لماذا؟', value: rec.rationale!),
          if (rec.evidence != null && rec.evidence!.isNotEmpty)
            _Detail(label: 'الدليل', value: rec.evidence!),
          if (rec.estimatedImpact != null && rec.estimatedImpact!.isNotEmpty)
            _Detail(label: 'الأثر المتوقع', value: rec.estimatedImpact!),
          const SizedBox(height: 8),
          Align(
            alignment: AlignmentDirectional.centerStart,
            child: FilledButton.tonalIcon(
              onPressed: onPackage,
              icon: const Icon(Icons.inventory_2_outlined, size: 18),
              label: Text(rec.packagePublicId != null
                  ? 'افتح حزمة التنفيذ'
                  : 'حوّلها لحزمة تنفيذ'),
            ),
          ),
        ],
      ),
    );
  }
}

class _Detail extends StatelessWidget {
  const _Detail({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(label,
              style: theme.textTheme.labelLarge
                  ?.copyWith(color: theme.colorScheme.primary)),
          const SizedBox(height: 2),
          Text(value, style: theme.textTheme.bodyMedium),
        ],
      ),
    );
  }
}
