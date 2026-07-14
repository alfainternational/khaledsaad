// موديلات الداشبورد والحساب — typed بدل الخرائط الخام.
//
// تعكس مخرجات:
// - WorkspaceDashboardController@show (onboarding_completed + dashboard).
// - DashboardResolver (nextStep, currentProject, stageProgress, toolPipeline,
//   briefAssessment.next_actions, recentToolRuns).
// - AccountController@show (user, account, workspace, profile, plan, options).

// ---- الداشبورد ----

/// لقطة استجابة الداشبورد: حالة الإعداد الأولي + بيانات الداشبورد.
class DashboardSnapshot {
  const DashboardSnapshot({this.onboardingCompleted, this.dashboard});

  /// `false` = لم يكتمل الإعداد ⇒ يجب التوجيه لشاشة الإعداد.
  /// `null` = المفتاح غير موجود ⇒ لا توجيه (نحافظ على سلوك المقارنة الصريحة).
  final bool? onboardingCompleted;
  final DashboardData? dashboard;

  factory DashboardSnapshot.fromJson(Map<String, dynamic> json) {
    final d = json['dashboard'];
    return DashboardSnapshot(
      onboardingCompleted:
          json['onboarding_completed'] is bool ? json['onboarding_completed'] as bool : null,
      dashboard: d is Map ? DashboardData.fromJson(Map<String, dynamic>.from(d)) : null,
    );
  }
}

/// لقطة الداشبورد الذكية لمساحة العمل.
class DashboardData {
  const DashboardData({
    this.nextStep,
    this.currentProjectName,
    this.currentStage,
    this.stageProgress = const [],
    this.toolPipeline = const [],
    this.recommendations = const [],
    this.recentToolRuns = const [],
  });

  final NextStep? nextStep;
  final String? currentProjectName;
  final int? currentStage;
  final List<StageProgress> stageProgress;
  final List<ToolPipelineStage> toolPipeline;

  /// توصيات تنفيذية من تقييم ملف المشروع (`briefAssessment.next_actions`).
  final List<String> recommendations;
  final List<RecentToolRun> recentToolRuns;

  /// اسم المرحلة الحالية من خريطة تقدّم المراحل.
  String? get currentStageLabel {
    final stage = currentStage;
    if (stage == null) return null;
    for (final s in stageProgress) {
      if (s.number == stage && s.label.isNotEmpty) return s.label;
    }
    return null;
  }

  /// نسبة إكمال المسار = مجموع الأدوات المنجزة ÷ إجماليها عبر كل المراحل.
  int? get pathCompletionPercent {
    if (toolPipeline.isEmpty) return null;
    var completed = 0;
    var total = 0;
    for (final s in toolPipeline) {
      completed += s.completed;
      total += s.total;
    }
    if (total == 0) return null;
    return ((completed / total) * 100).round();
  }

  factory DashboardData.fromJson(Map<String, dynamic> json) {
    final next = json['nextStep'];
    final proj = json['currentProject'];
    final brief = json['briefAssessment'];
    return DashboardData(
      nextStep: next is Map ? NextStep.fromJson(Map<String, dynamic>.from(next)) : null,
      currentProjectName: proj is Map ? proj['name']?.toString() : null,
      currentStage: proj is Map && proj['stage'] is num ? (proj['stage'] as num).toInt() : null,
      stageProgress: _mapList(json['stageProgress'], StageProgress.fromJson),
      toolPipeline: _mapList(json['toolPipeline'], ToolPipelineStage.fromJson),
      recommendations: (brief is Map && brief['next_actions'] is List)
          ? (brief['next_actions'] as List)
              .map((e) => e.toString())
              .where((s) => s.trim().isNotEmpty)
              .toList()
          : const [],
      recentToolRuns: _mapList(json['recentToolRuns'], RecentToolRun.fromJson),
    );
  }
}

/// بطاقة «الخطوة التالية» — من `NextStepRecommendationService`.
class NextStep {
  const NextStep({
    required this.title,
    this.summary = '',
    this.details = const [],
    this.actionLabel = 'ابدأ الآن',
    this.actionType,
    this.toolCode,
    this.projectPublicId,
  });

  final String title;
  final String summary;
  final List<String> details;
  final String actionLabel;
  final String? actionType;
  final String? toolCode;
  final String? projectPublicId;

  factory NextStep.fromJson(Map<String, dynamic> json) => NextStep(
        title: json['title']?.toString() ?? 'خطوتك التالية',
        summary: json['summary']?.toString() ?? '',
        details: _stringList(json['details']),
        actionLabel: json['action_label']?.toString() ?? 'ابدأ الآن',
        actionType: json['action_type']?.toString(),
        toolCode: json['tool_code']?.toString(),
        projectPublicId: json['project_public_id']?.toString(),
      );
}

/// عنصر تقدّم مرحلة — من `DashboardResolver.stageProgress`.
class StageProgress {
  const StageProgress({required this.number, required this.label});

  final int number;
  final String label;

  factory StageProgress.fromJson(Map<String, dynamic> json) => StageProgress(
        number: (json['number'] as num?)?.toInt() ?? 0,
        label: json['label']?.toString() ?? '',
      );
}

/// عنصر خط أنابيب الأدوات — من `DashboardResolver.toolPipeline`.
class ToolPipelineStage {
  const ToolPipelineStage({this.completed = 0, this.total = 0});

  final int completed;
  final int total;

  factory ToolPipelineStage.fromJson(Map<String, dynamic> json) => ToolPipelineStage(
        completed: (json['completed'] as num?)?.toInt() ?? 0,
        total: (json['total'] as num?)?.toInt() ?? 0,
      );
}

/// تشغيل أداة حديث — من `DashboardResolver.recentToolRuns`.
class RecentToolRun {
  const RecentToolRun({required this.toolCode, this.toolName, this.projectName});

  final String toolCode;
  final String? toolName;
  final String? projectName;

  factory RecentToolRun.fromJson(Map<String, dynamic> json) {
    final tool = json['tool'];
    final project = json['project'];
    return RecentToolRun(
      toolCode: json['tool_code']?.toString() ?? '',
      toolName: tool is Map ? tool['name']?.toString() : null,
      projectName: project is Map ? project['name']?.toString() : null,
    );
  }
}

// ---- الحساب ----

/// خيار قائمة منسدلة موحّد (قيمة + عنوان).
class SelectOption {
  const SelectOption(this.value, this.label);

  final String value;
  final String label;
}

/// نظرة الحساب الكاملة — من `AccountController@show`.
class AccountOverview {
  const AccountOverview({
    this.name = '',
    this.locale = 'ar',
    this.accountName = '',
    this.billingEmail = '',
    this.workspaceName = '',
    this.workspaceType,
    this.planName,
    this.profile = const AccountProfile(),
    this.options = const {},
  });

  final String name;
  final String locale;
  final String accountName;
  final String billingEmail;
  final String workspaceName;
  final String? workspaceType;
  final String? planName;
  final AccountProfile profile;

  /// خيارات القوائم من الخادم، مفهرسة بمفتاح الحقل في الواجهة.
  final Map<String, List<SelectOption>> options;

  factory AccountOverview.fromJson(Map<String, dynamic> json) {
    final user = _mapOf(json['user']);
    final account = _mapOf(json['account']);
    final workspace = _mapOf(json['workspace']);
    final plan = _mapOf(json['plan']);
    final opts = _mapOf(json['options']);
    return AccountOverview(
      name: user['name']?.toString() ?? '',
      locale: user['locale']?.toString() ?? 'ar',
      accountName: account['name']?.toString() ?? '',
      billingEmail: account['billing_email']?.toString() ?? '',
      workspaceName: workspace['name']?.toString() ?? '',
      workspaceType: workspace['type']?.toString(),
      planName: plan['name']?.toString(),
      profile: AccountProfile.fromJson(_mapOf(json['profile'])),
      options: {
        'persona': _parseOptions(opts['personas']),
        'awareness_level': _parseOptions(opts['awareness_levels']),
        'primary_goal': _parseOptions(opts['goals']),
        'recommended_path': _parseOptions(opts['paths']),
        'content_locale': _parseOptions(opts['content_locales']),
      },
    );
  }
}

/// الملف التسويقي داخل نظرة الحساب.
class AccountProfile {
  const AccountProfile({
    this.audience = '',
    this.country = '',
    this.currentChallenge = '',
    this.persona,
    this.awarenessLevel,
    this.primaryGoal,
    this.recommendedPath,
    this.contentLocale,
  });

  final String audience;
  final String country;
  final String currentChallenge;
  final String? persona;
  final String? awarenessLevel;
  final String? primaryGoal;
  final String? recommendedPath;
  final String? contentLocale;

  factory AccountProfile.fromJson(Map<String, dynamic> json) => AccountProfile(
        audience: json['audience']?.toString() ?? '',
        country: json['country']?.toString() ?? '',
        currentChallenge: json['current_challenge']?.toString() ?? '',
        persona: json['persona']?.toString(),
        awarenessLevel: json['awareness_level']?.toString(),
        primaryGoal: json['primary_goal']?.toString(),
        recommendedPath: json['recommended_path']?.toString(),
        contentLocale: json['content_locale']?.toString(),
      );
}

// ---- مساعدات تحويل ----

Map<String, dynamic> _mapOf(dynamic v) =>
    v is Map ? Map<String, dynamic>.from(v) : <String, dynamic>{};

List<String> _stringList(dynamic v) => v is List
    ? v.map((e) => e.toString()).where((s) => s.trim().isNotEmpty).toList()
    : const <String>[];

List<T> _mapList<T>(dynamic v, T Function(Map<String, dynamic>) fromJson) => v is List
    ? v
        .whereType<Map>()
        .map((e) => fromJson(Map<String, dynamic>.from(e)))
        .toList()
    : const [];

/// يحوّل خيارات الخادم (خريطة {key: label} أو قائمة [{value/key, label}]) لقائمة موحّدة.
List<SelectOption> _parseOptions(dynamic raw) {
  if (raw is Map) {
    return raw.entries.map((e) {
      final v = e.value;
      final label = v is Map ? (v['label'] ?? v['title'] ?? e.key).toString() : v.toString();
      return SelectOption(e.key.toString(), label);
    }).toList();
  }
  if (raw is List) {
    return raw.whereType<Map>().map((e) {
      final value = (e['value'] ?? e['key'] ?? '').toString();
      final label = (e['label'] ?? e['title'] ?? value).toString();
      return SelectOption(value, label);
    }).toList();
  }
  return const [];
}
