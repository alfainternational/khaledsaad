import 'package:get/get.dart';

import '../../core/error/api_exception.dart';
import '../../data/models/project_model.dart';
import '../../data/repositories/project_repository.dart';
import '../../data/services/workspace_service.dart';

class ProjectDetailController extends GetxController {
  ProjectDetailController(
    this._projects,
    this._workspaces,
    this.projectPublicId,
  );

  final ProjectRepository _projects;
  final WorkspaceService _workspaces;
  final String projectPublicId;

  final Rxn<ProjectModel> project = Rxn<ProjectModel>();
  final isLoading = false.obs;
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
      project.value = await _projects.show(ws, projectPublicId);
    } on ApiException catch (e) {
      error.value = e.message;
    } finally {
      isLoading.value = false;
    }
  }
}
