import 'package:get/get.dart';

import '../../app/routes/app_routes.dart';
import '../../core/error/api_exception.dart';
import '../../core/l10n/ar_labels.dart';
import '../../data/models/dashboard_models.dart';
import '../../data/repositories/auth_repository.dart';
import '../../data/repositories/collab_repository.dart';
import '../../data/services/session_service.dart';
import '../../data/services/workspace_service.dart';
import '../shell/home_shell.dart';

class DashboardController extends GetxController {
  DashboardController(this._workspaces, this._auth, this._session, this._collab);

  final WorkspaceService _workspaces;
  final AuthRepository _auth;
  final SessionService _session;
  final CollabRepository _collab;

  /// لقطة الداشبورد الذكية من الخادم.
  final dashboardData = Rxn<DashboardData>();

  /// اسم المستخدم للتحية (يُجلب مرة واحدة، بأفضل جهد).
  final userName = RxnString();

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
      if (userName.value == null) {
        try {
          userName.value = (await _auth.me()).name;
        } on ApiException catch (_) {
          // التحية اختيارية — نتجاهل فشل جلب الاسم.
        }
      }
      final ws = _workspaces.activeId;
      if (ws != null) {
        final snapshot = await _collab.dashboard(ws);
        // لم يكتمل الإعداد الأولي → وجّه لشاشة الإعداد.
        if (snapshot.onboardingCompleted == false) {
          Get.offAllNamed(Routes.onboarding);
          return;
        }
        dashboardData.value = snapshot.dashboard;
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

  void openProjects() {
    if (Get.isRegistered<HomeShellController>()) {
      Get.find<HomeShellController>().go(1);
    } else {
      Get.toNamed(Routes.projects);
    }
  }

  /// بطاقة «الخطوة التالية» من لقطة الداشبورد الذكية.
  NextStep? get nextStep => dashboardData.value?.nextStep;

  /// اسم المشروع الحالي المعروض في اللقطة (إن وُجد).
  String? get currentProjectName => dashboardData.value?.currentProjectName;

  /// رقم المرحلة الحالية للمشروع النشط.
  int? get currentStage => dashboardData.value?.currentStage;

  /// اسم المرحلة الحالية من خريطة تقدّم المراحل.
  String? get currentStageLabel => dashboardData.value?.currentStageLabel;

  /// نسبة إكمال المسار = مجموع الأدوات المنجزة ÷ إجماليها عبر كل المراحل.
  int? get pathCompletionPercent => dashboardData.value?.pathCompletionPercent;

  /// توصيات تنفيذية من تقييم ملف المشروع (إن توفّرت).
  List<String> get recommendations =>
      dashboardData.value?.recommendations ?? const [];

  /// آخر النشاط: تشغيلات الأدوات الأخيرة مع اسم الأداة والمشروع.
  List<({String title, String subtitle})> get recentActivity {
    final runs = dashboardData.value?.recentToolRuns ?? const [];
    final items = <({String title, String subtitle})>[];
    for (final r in runs) {
      final toolName =
          r.toolName ?? ArLabels.toolNames[r.toolCode] ?? r.toolCode;
      if (toolName.isEmpty) continue;
      items.add((title: toolName, subtitle: r.projectName ?? ''));
    }
    return items;
  }

  /// ينفّذ إجراء الخطوة التالية: يفتح الأداة المرشّحة مباشرة إن وُجدت،
  /// وإلا يوجّه للوجهة المناسبة (الإعداد أو المشاريع).
  void openNextStep() {
    final next = nextStep;
    if (next == null) {
      openProjects();
      return;
    }
    final type = next.actionType;
    final toolCode = next.toolCode ?? '';
    final projectId = next.projectPublicId ?? '';

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
    // انتقال لأدوات المرحلة التالية: افتح قائمة أدوات المشروع مباشرة.
    if (type == 'tools_index' && projectId.isNotEmpty) {
      Get.toNamed(Routes.projectTools, arguments: projectId);
      return;
    }
    // إنشاء أول مشروع (create_project) أو أي حالة أخرى: شاشة المشاريع (بها زر الإنشاء).
    openProjects();
  }
}
