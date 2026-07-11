import 'package:get/get.dart';

import '../../app/routes/app_routes.dart';
import '../../core/error/api_exception.dart';
import '../../data/models/project_model.dart';
import '../../data/repositories/project_repository.dart';
import '../../data/services/workspace_service.dart';

class ProjectsController extends GetxController {
  ProjectsController(this._repo, this._workspaces);

  final ProjectRepository _repo;
  final WorkspaceService _workspaces;

  final projects = <ProjectModel>[].obs;
  final isLoading = false.obs;
  final error = RxnString();
  final statusFilter = RxnString();
  final stageFilter = RxnInt();

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
      final rows = await _repo.list(
        ws,
        status: statusFilter.value,
        stage: stageFilter.value,
      );
      projects.assignAll(rows);
    } on ApiException catch (e) {
      error.value = e.message;
    } finally {
      isLoading.value = false;
    }
  }

  void setStatus(String? status) {
    statusFilter.value = status;
    load();
  }

  void openProject(ProjectModel project) {
    Get.toNamed(Routes.projectDetail, arguments: project.publicId);
  }
}
