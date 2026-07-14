import 'dart:async';

import 'package:get/get.dart';

import '../../app/routes/app_routes.dart';
import '../../core/error/api_exception.dart';
import '../../data/models/studio_models.dart';
import '../../data/repositories/studio_repository.dart';
import '../../data/services/workspace_service.dart';
import '../shared/widgets/ui_feedback.dart';

class StudioController extends GetxController {
  StudioController(this._repo, this._workspaces);

  final StudioRepository _repo;
  final WorkspaceService _workspaces;

  final templates = <AiTemplate>[].obs;
  final generations = <StudioGeneration>[].obs;
  final isLoading = false.obs;
  final isGenerating = false.obs;
  final error = RxnString();

  String? get workspaceId => _workspaces.activeId;

  @override
  void onReady() {
    super.onReady();
    load();
  }

  Future<void> load() async {
    final ws = _workspaces.activeId;
    if (ws == null) {
      error.value = 'لا توجد مساحة عمل نشطة.';
      return;
    }
    isLoading.value = true;
    error.value = null;
    try {
      final results = await Future.wait([
        _repo.templates(ws),
        _repo.generations(ws),
      ]);
      templates.assignAll(results[0] as List<AiTemplate>);
      generations.assignAll(results[1] as List<StudioGeneration>);
    } on ApiException catch (e) {
      error.value = e.message;
    } finally {
      isLoading.value = false;
    }
  }

  /// يولّد مخرجاً ويعيده (أو يرمي استثناءً معالَجاً في الواجهة).
  Future<StudioGeneration?> generate({
    required int templateId,
    String? projectPublicId,
    String? brief,
    bool freshCopy = false,
  }) async {
    final ws = _workspaces.activeId;
    if (ws == null) return null;
    isGenerating.value = true;
    try {
      final generation = await _repo.generate(
        ws,
        templateId: templateId,
        projectId: projectPublicId,
        brief: brief,
        freshCopy: freshCopy,
      );
      generations.insert(0, generation);
      return generation;
    } finally {
      isGenerating.value = false;
    }
  }

  /// مؤقّتات حذف مؤجّلة (للسماح بالتراجع قبل الحذف الفعلي على الخادم).
  final _pendingDeletes = <String, Timer>{};

  /// حذف تفاؤلي: يزيل المخرج من القائمة فوراً ويعرض «تراجع». يُنفَّذ الحذف
  /// على الخادم بعد انقضاء مهلة التراجع ما لم يتراجع المستخدم.
  void deleteGeneration(StudioGeneration generation) {
    final index =
        generations.indexWhere((g) => g.publicId == generation.publicId);
    if (index < 0) return;
    generations.removeAt(index);

    final timer = Timer(const Duration(seconds: 5), () async {
      _pendingDeletes.remove(generation.publicId);
      final ws = _workspaces.activeId;
      if (ws == null) return;
      try {
        await _repo.delete(ws, generation.publicId);
      } on ApiException {
        // فشل الحذف على الخادم — نعيد المخرج للقائمة حتى لا يضيع صامتاً.
        if (!generations.any((g) => g.publicId == generation.publicId)) {
          generations.insert(index.clamp(0, generations.length), generation);
        }
      }
    });
    _pendingDeletes[generation.publicId] = timer;

    UiFeedback.undoable('حُذف المخرج', onUndo: () {
      _pendingDeletes.remove(generation.publicId)?.cancel();
      if (!generations.any((g) => g.publicId == generation.publicId)) {
        generations.insert(index.clamp(0, generations.length), generation);
      }
    });
  }

  @override
  void onClose() {
    for (final timer in _pendingDeletes.values) {
      timer.cancel();
    }
    _pendingDeletes.clear();
    super.onClose();
  }

  void openGeneration(StudioGeneration generation) {
    Get.toNamed(Routes.studioGeneration, arguments: generation.publicId);
  }
}
