import 'package:get/get.dart';

import '../../app/routes/app_routes.dart';
import '../../core/error/api_exception.dart';
import '../../core/l10n/ar_labels.dart';
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
  final dashboardData = Rxn<Map<String, dynamic>>();

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

  void openProjects() {
    if (Get.isRegistered<HomeShellController>()) {
      Get.find<HomeShellController>().go(1);
    } else {
      Get.toNamed(Routes.projects);
    }
  }

  /// بطاقة «الخطوة التالية» من لقطة الداشبورد الذكية.
  Map<String, dynamic>? get nextStep {
    final raw = dashboardData.value?['nextStep'];
    return raw is Map ? Map<String, dynamic>.from(raw) : null;
  }

  Map<String, dynamic> get _data =>
      dashboardData.value ?? const <String, dynamic>{};

  /// اسم المشروع الحالي المعروض في اللقطة (إن وُجد).
  String? get currentProjectName {
    final p = _data['currentProject'];
    return p is Map ? p['name']?.toString() : null;
  }

  /// رقم المرحلة الحالية للمشروع النشط.
  int? get currentStage {
    final p = _data['currentProject'];
    if (p is Map && p['stage'] is num) return (p['stage'] as num).toInt();
    return null;
  }

  /// اسم المرحلة الحالية من خريطة تقدّم المراحل.
  String? get currentStageLabel {
    final stage = currentStage;
    if (stage == null) return null;
    final progress = _data['stageProgress'];
    if (progress is List) {
      for (final s in progress) {
        if (s is Map && (s['number'] as num?)?.toInt() == stage) {
          final label = s['label']?.toString();
          if (label != null && label.isNotEmpty) return label;
        }
      }
    }
    return null;
  }

  /// نسبة إكمال المسار = مجموع الأدوات المنجزة ÷ إجماليها عبر كل المراحل.
  int? get pathCompletionPercent {
    final pipeline = _data['toolPipeline'];
    if (pipeline is! List || pipeline.isEmpty) return null;
    var completed = 0;
    var total = 0;
    for (final s in pipeline) {
      if (s is Map) {
        completed += (s['completed'] as num?)?.toInt() ?? 0;
        total += (s['total'] as num?)?.toInt() ?? 0;
      }
    }
    if (total == 0) return null;
    return ((completed / total) * 100).round();
  }

  /// توصيات تنفيذية من تقييم ملف المشروع (إن توفّرت).
  List<String> get recommendations {
    final brief = _data['briefAssessment'];
    if (brief is Map && brief['next_actions'] is List) {
      return (brief['next_actions'] as List)
          .map((e) => e.toString())
          .where((s) => s.trim().isNotEmpty)
          .toList();
    }
    return const [];
  }

  /// آخر النشاط: تشغيلات الأدوات الأخيرة مع اسم الأداة والمشروع.
  List<({String title, String subtitle})> get recentActivity {
    final runs = _data['recentToolRuns'];
    if (runs is! List) return const [];
    final items = <({String title, String subtitle})>[];
    for (final r in runs) {
      if (r is! Map) continue;
      final toolCode = r['tool_code']?.toString() ?? '';
      final toolName =
          (r['tool'] is Map ? (r['tool'] as Map)['name']?.toString() : null) ??
              ArLabels.toolNames[toolCode] ??
              toolCode;
      if (toolName.isEmpty) continue;
      final projectName =
          r['project'] is Map ? (r['project'] as Map)['name']?.toString() : null;
      items.add((title: toolName, subtitle: projectName ?? ''));
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
    // انتقال لأدوات المرحلة التالية: افتح قائمة أدوات المشروع مباشرة.
    if (type == 'tools_index' && projectId.isNotEmpty) {
      Get.toNamed(Routes.projectTools, arguments: projectId);
      return;
    }
    // إنشاء أول مشروع (create_project) أو أي حالة أخرى: شاشة المشاريع (بها زر الإنشاء).
    openProjects();
  }
}
