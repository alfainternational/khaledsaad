import 'package:get/get.dart';

import '../../app/routes/app_routes.dart';
import '../../core/error/api_exception.dart';
import '../../core/l10n/ar_labels.dart';
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

  /// بطاقة «الخطوة التالية» من لقطة الداشبورد الذكية.
  Map<String, dynamic>? get nextStep {
    final raw = dashboardData.value?['nextStep'];
    return raw is Map ? Map<String, dynamic>.from(raw) : null;
  }

  /// ينفّذ إجراء الخطوة التالية: يفتح الأداة المرشّحة مباشرة إن وُجدت،
  /// وإلا يوجّه للوجهة المناسبة (الإعداد أو المشاريع).
  void openNextStep() {
    final next = nextStep;
    if (next == null) {
      openProjects();
      return;
    }
    final type = next['action_type']?.toString();
    final toolCode = next['tool_code']?.toString() ?? '';
    final projectId = next['project_public_id']?.toString() ?? '';

    if (type == 'tool' && toolCode.isNotEmpty && projectId.isNotEmpty) {
      Get.toNamed(Routes.toolRunner, arguments: {
        'project_public_id': projectId,
        'tool_code': toolCode,
        'tool_name': ArLabels.toolNames[toolCode] ?? toolCode,
      });
      return;
    }
    if (type == 'onboarding') {
      Get.toNamed(Routes.onboarding);
      return;
    }
    openProjects();
  }
}
