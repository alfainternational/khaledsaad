import 'package:get/get.dart';

import '../../app/routes/app_routes.dart';
import '../../core/error/api_exception.dart';
import '../../data/models/studio_models.dart';
import '../../data/repositories/studio_repository.dart';
import '../../data/services/workspace_service.dart';

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
      );
      generations.insert(0, generation);
      return generation;
    } finally {
      isGenerating.value = false;
    }
  }

  Future<void> deleteGeneration(StudioGeneration generation) async {
    final ws = _workspaces.activeId;
    if (ws == null) return;
    await _repo.delete(ws, generation.publicId);
    generations.removeWhere((g) => g.publicId == generation.publicId);
  }

  void openGeneration(StudioGeneration generation) {
    Get.toNamed(Routes.studioGeneration, arguments: generation.publicId);
  }
}
