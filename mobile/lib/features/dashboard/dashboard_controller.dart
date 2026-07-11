import 'package:get/get.dart';

import '../../app/routes/app_routes.dart';
import '../../core/error/api_exception.dart';
import '../../data/repositories/auth_repository.dart';
import '../../data/repositories/collab_repository.dart';
import '../../data/services/session_service.dart';
import '../../data/services/workspace_service.dart';

class DashboardController extends GetxController {
  DashboardController(this._workspaces, this._auth, this._session, this._collab);

  final WorkspaceService _workspaces;
  final AuthRepository _auth;
  final SessionService _session;
  final CollabRepository _collab;

  /// لقطة الداشبورد الذكية من الخادم.
  final dashboardData = Rxn<Map<String, dynamic>>();

  WorkspaceService get workspaces => _workspaces;

  final error = RxnString();
  final isLoading = false.obs;

  @override
  void onReady() {
    super.onReady();
    load();
  }

  Future<void> load() async {
    isLoading.value = true;
    error.value = null;
    try {
      await _workspaces.loadWorkspaces();
      final ws = _workspaces.activeId;
      if (ws != null) {
        final data = await _collab.dashboard(ws);
        // لم يكتمل الإعداد الأولي → وجّه لشاشة الإعداد.
        if (data['onboarding_completed'] == false) {
          Get.offAllNamed(Routes.onboarding);
          return;
        }
        dashboardData.value = data['dashboard'] is Map
            ? Map<String, dynamic>.from(data['dashboard'] as Map)
            : null;
      }
    } on ApiException catch (e) {
      error.value = e.message;
    } finally {
      isLoading.value = false;
    }
  }

  Future<void> logout() async {
    try {
      await _auth.logout();
    } on ApiException catch (_) {
      // نتابع تسجيل الخروج محلياً حتى لو فشل الطلب.
    }
    await _session.clear();
    Get.offAllNamed(Routes.login);
  }

  void openProjects() => Get.toNamed(Routes.projects);
}
