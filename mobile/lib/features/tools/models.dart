/// نماذج تقرأ حمولات العارضين في Laravel حرفيًا.
/// أي حقل هنا له مقابل في app/Support/Presentation.
library;

class ToolCard {
  const ToolCard({
    required this.key,
    required this.title,
    required this.description,
    required this.category,
    required this.isRunnable,
    required this.statusLabel,
    this.pain,
    this.promise,
    this.audience,
    this.durationMinutes,
  });

  factory ToolCard.fromJson(Map<String, dynamic> json) => ToolCard(
    key: json['key'] as String,
    title: json['title'] as String,
    description: json['description'] as String,
    category: json['category'] as String,
    isRunnable: json['is_runnable'] as bool,
    statusLabel: json['status_label'] as String,
    // لغة العميل، مطابقة لما يعرضه ToolPresenter::card في الويب.
    pain: json['pain'] as String?,
    promise: json['promise'] as String?,
    audience: json['audience'] as String?,
    durationMinutes: json['duration_minutes'] as int?,
  );

  final String key;
  final String title;
  final String description;
  final String category;
  final bool isRunnable;
  final String statusLabel;
  final String? pain;
  final String? promise;
  final String? audience;
  final int? durationMinutes;

  /// ما يُعرض للعميل: وعد الأداة إن وُجد، وإلا وصفها.
  String get headline =>
      (promise != null && promise!.isNotEmpty) ? promise! : description;
}

class ToolDetail {
  const ToolDetail({
    required this.card,
    required this.stepCount,
    required this.inputs,
    required this.outputs,
  });

  factory ToolDetail.fromJson(Map<String, dynamic> json) => ToolDetail(
    card: ToolCard.fromJson(json),
    stepCount: json['step_count'] as int? ?? 0,
    inputs: (json['inputs'] as List? ?? const [])
        .map((e) => e.toString())
        .toList(),
    outputs: (json['outputs'] as List? ?? const [])
        .map((e) => e.toString())
        .toList(),
  );

  final ToolCard card;
  final int stepCount;
  final List<String> inputs;
  final List<String> outputs;
}

class FieldOption {
  const FieldOption({required this.value, required this.label});

  factory FieldOption.fromJson(Map<String, dynamic> json) => FieldOption(
    value: json['value'].toString(),
    label: json['label'].toString(),
  );

  final String value;
  final String label;
}

class ToolFieldModel {
  const ToolFieldModel({
    required this.key,
    required this.label,
    required this.type,
    required this.required,
    required this.options,
    this.help,
    this.why,
    this.example,
    this.value,
    this.isKnown = false,
    this.benchmark,
  });

  factory ToolFieldModel.fromJson(Map<String, dynamic> json) => ToolFieldModel(
    key: json['key'] as String,
    label: json['label'] as String,
    type: json['type'] as String,
    required: json['required'] as bool? ?? true,
    help: json['help'] as String?,
    why: json['why'] as String?,
    example: json['example'] as String?,
    options: (json['options'] as List? ?? const [])
        .map((e) => FieldOption.fromJson(Map<String, dynamic>.from(e as Map)))
        .toList(),
    value: json['value'],
    // معروف مسبقًا: عرفه النظام من إجابة سابقة، فلا يظهر كأن المستخدم كتبه الآن.
    // كان التطبيق يتجاهله ويملأ الحقل بصمت — أسوأ من الويب الذي يعلن المصدر.
    isKnown: json['is_known'] as bool? ?? false,
    benchmark: json['benchmark']?.toString(),
  );

  final String key;
  final String label;
  final String type;
  final bool required;
  final String? help;
  final String? why;
  final String? example;
  final List<FieldOption> options;
  final dynamic value;

  /// أجاب عنه المستخدم في مكان آخر فورِث هنا — يُعرض موسومًا لا صامتًا.
  final bool isKnown;

  /// رقم مرجعي للمقارنة بجانب الحقل، حين يوفّره العارض.
  final String? benchmark;

  List<String> get selectedValues => value is List
      ? (value as List).map((e) => e.toString()).toList()
      : const <String>[];

  String get textValue => value == null ? '' : value.toString();
}

class WizardStep {
  const WizardStep({
    required this.step,
    required this.title,
    required this.fields,
  });

  factory WizardStep.fromJson(Map<String, dynamic> json) => WizardStep(
    step: json['step'] as int,
    title: json['title'] as String,
    fields: (json['fields'] as List)
        .map(
          (e) => ToolFieldModel.fromJson(Map<String, dynamic>.from(e as Map)),
        )
        .toList(),
  );

  final int step;
  final String title;
  final List<ToolFieldModel> fields;
}

/// كفاية إجابة مفتوحة كما تُقاس أثناء الكتابة — حتميّة، بلا تكلفة ولا حفظ.
///
/// نظير قاعدة الويب: صاحب النشاط يرى أن وصفه عامٌّ وهو ما زال أمام السؤال،
/// لا في تقرير لا يستطيع تعديله. «أجاب» لا يساوي «أجاب بما يكفي».
class AnswerFitnessResult {
  const AnswerFitnessResult({
    required this.score,
    required this.verdict,
    required this.gaps,
  });

  factory AnswerFitnessResult.fromJson(Map<String, dynamic> json) =>
      AnswerFitnessResult(
        score: json['score'] as int? ?? 0,
        verdict: json['verdict']?.toString() ?? '',
        gaps: (json['gaps'] as List? ?? const [])
            .map((item) => item.toString())
            .toList(),
      );

  final int score;
  final String verdict;
  final List<String> gaps;

  bool get isSufficient => verdict == 'sufficient';

  /// جملة قصيرة بلغة المستخدم — لا رمز داخلي. فرضية منهجية دائمًا (inferred).
  String get label => switch (verdict) {
    'sufficient' => 'إجابة واضحة بما يكفي للتشخيص.',
    'partial' => 'مفيدة، وتحتمل تحديدًا أكثر.',
    _ => 'عامة — حدّدها أكثر ليقيسها التشخيص بدقة.',
  };
}

/// مقترح إجابة واحد: نصّ جاهز للزر + سبب ملاءمته لهذا النشاط تحديدًا.
class AssistSuggestion {
  const AssistSuggestion({
    required this.label,
    required this.value,
    required this.why,
  });

  factory AssistSuggestion.fromJson(Map<String, dynamic> json) =>
      AssistSuggestion(
        label: json['label']?.toString() ?? '',
        value: json['value']?.toString() ?? '',
        why: json['why']?.toString() ?? '',
      );

  final String label;
  final String value;
  final String why;
}

/// مسوّدة مساعدة على سؤال: دليل ومقترحات مبنية على ما وصفه صاحب النشاط.
///
/// فرضية موسومة دائمًا (§١٣): تُراجَع وتُعدَّل قبل اعتمادها، ولا تُخترع خيارًا
/// خارج خيارات السؤال. تُولَّد بنموذج لغوي فتُحجز من سقف المساحة قبل الطلب (§٤.٤).
class AssistDraftModel {
  const AssistDraftModel({
    required this.guide,
    required this.suggestions,
    this.assumptionLabel,
  });

  factory AssistDraftModel.fromJson(Map<String, dynamic> json) =>
      AssistDraftModel(
        guide: json['guide']?.toString() ?? '',
        suggestions: (json['suggestions'] as List? ?? const [])
            .map(
              (item) =>
                  AssistSuggestion.fromJson(Map<String, dynamic>.from(item as Map)),
            )
            .toList(),
        assumptionLabel: json['assumption_label']?.toString(),
      );

  final String guide;
  final List<AssistSuggestion> suggestions;
  final String? assumptionLabel;

  bool get isEmpty => guide.trim().isEmpty && suggestions.isEmpty;
}

class RunStage {
  const RunStage({
    required this.key,
    required this.label,
    required this.status,
    required this.statusLabel,
    this.error,
  });

  factory RunStage.fromJson(Map<String, dynamic> json) => RunStage(
    key: json['key'] as String,
    label: json['label'] as String,
    status: json['status'] as String,
    statusLabel: json['status_label'] as String,
    error: json['error'] as String?,
  );

  final String key;
  final String label;
  final String status;
  final String statusLabel;
  final String? error;
}

class HybridInsightSummary {
  const HybridInsightSummary({
    required this.completenessPercent,
    required this.missingCount,
    required this.missing,
    required this.agencyReadinessPercent,
    required this.agencyReadinessLabel,
    required this.agencyMissing,
  });

  factory HybridInsightSummary.fromJson(Map<String, dynamic> json) =>
      HybridInsightSummary(
        completenessPercent: json['completeness_percent'] as int? ?? 0,
        missingCount: json['missing_count'] as int? ?? 0,
        missing: (json['missing'] as List? ?? const [])
            .map((item) => item.toString())
            .toList(),
        agencyReadinessPercent: json['agency_readiness_percent'] as int? ?? 0,
        agencyReadinessLabel: json['agency_readiness_label'] as String? ?? '',
        agencyMissing: (json['agency_missing'] as List? ?? const [])
            .map((item) => item.toString())
            .toList(),
      );

  final int completenessPercent;
  final int missingCount;
  final List<String> missing;
  final int agencyReadinessPercent;
  final String agencyReadinessLabel;
  final List<String> agencyMissing;
}

class HybridSignal {
  const HybridSignal({
    required this.type,
    required this.title,
    required this.description,
    required this.basis,
  });

  factory HybridSignal.fromJson(Map<String, dynamic> json) => HybridSignal(
    type: json['type'] as String? ?? 'info',
    title: json['title'] as String? ?? '',
    description: json['description'] as String? ?? '',
    basis: json['basis'] as String? ?? '',
  );

  final String type;
  final String title;
  final String description;
  final String basis;
}

class PreliminaryInsight {
  const PreliminaryInsight({
    required this.status,
    required this.label,
    required this.meaning,
    required this.riskOrOpportunity,
    required this.recommendation,
    required this.deepenQuestion,
  });

  factory PreliminaryInsight.fromJson(Map<String, dynamic> json) =>
      PreliminaryInsight(
        status: json['status'] as String? ?? 'not_requested',
        label: json['label'] as String? ?? 'مؤشر أولي',
        meaning: json['meaning'] as String? ?? '',
        riskOrOpportunity: json['risk_or_opportunity'] as String? ?? '',
        recommendation: json['recommendation'] as String? ?? '',
        deepenQuestion: json['deepen_question'] as String? ?? '',
      );

  final String status;
  final String label;
  final String meaning;
  final String riskOrOpportunity;
  final String recommendation;
  final String deepenQuestion;

  bool get isReady => status == 'ready';
}

class HybridInsights {
  const HybridInsights({
    required this.summary,
    required this.signals,
    required this.preliminary,
  });

  factory HybridInsights.fromJson(Map<String, dynamic> json) => HybridInsights(
    summary: HybridInsightSummary.fromJson(
      Map<String, dynamic>.from(json['summary'] as Map? ?? const {}),
    ),
    signals: (json['signals'] as List? ?? const [])
        .whereType<Map>()
        .map(
          (signal) => HybridSignal.fromJson(Map<String, dynamic>.from(signal)),
        )
        .toList(),
    preliminary: PreliminaryInsight.fromJson(
      Map<String, dynamic>.from(json['preliminary'] as Map? ?? const {}),
    ),
  );

  final HybridInsightSummary summary;
  final List<HybridSignal> signals;
  final PreliminaryInsight preliminary;
}

class ToolRunModel {
  const ToolRunModel({
    required this.uuid,
    required this.status,
    required this.statusLabel,
    required this.currentStep,
    required this.isTerminal,
    required this.progressPercent,
    required this.completenessPercent,
    required this.toolTitle,
    required this.projectName,
    required this.projectSlug,
    required this.steps,
    required this.stages,
    this.baseScore,
    this.reportId,
    this.failureReason,
    this.insights,
  });

  factory ToolRunModel.fromJson(Map<String, dynamic> json) => ToolRunModel(
    uuid: json['uuid'] as String,
    status: json['status'] as String,
    statusLabel: json['status_label'] as String,
    currentStep: json['current_step'] as int? ?? 1,
    isTerminal: json['is_terminal'] as bool? ?? false,
    progressPercent: json['progress_percent'] as int? ?? 0,
    completenessPercent: json['completeness_percent'] as int? ?? 0,
    baseScore: json['base_score'] as int?,
    reportId: json['report_id'] as int?,
    failureReason: json['failure_reason'] as String?,
    insights: json['insights'] is Map
        ? HybridInsights.fromJson(
            Map<String, dynamic>.from(json['insights'] as Map),
          )
        : null,
    toolTitle: (json['tool'] as Map?)?['title']?.toString() ?? '',
    projectName: (json['project'] as Map?)?['name']?.toString() ?? '',
    projectSlug: (json['project'] as Map?)?['slug']?.toString() ?? '',
    steps: (json['steps'] as List? ?? const [])
        .map((e) => WizardStep.fromJson(Map<String, dynamic>.from(e as Map)))
        .toList(),
    stages: (json['stages'] as List? ?? const [])
        .map((e) => RunStage.fromJson(Map<String, dynamic>.from(e as Map)))
        .toList(),
  );

  final String uuid;
  final String status;
  final String statusLabel;
  final int currentStep;
  final bool isTerminal;
  final int progressPercent;
  final int completenessPercent;
  final int? baseScore;
  final int? reportId;
  final String? failureReason;
  final HybridInsights? insights;
  final String toolTitle;
  final String projectName;

  /// معرّف النشاط في المسارات — يحتاجه رفع الصوت وأي نداء يخصّ النشاط لا التشغيل.
  final String projectSlug;
  final List<WizardStep> steps;
  final List<RunStage> stages;
}

class Preflight {
  const Preflight({
    required this.missing,
    required this.percent,
    required this.assumptions,
  });

  factory Preflight.fromJson(Map<String, dynamic> json) => Preflight(
    missing: (json['missing'] as List? ?? const [])
        .map((e) => e.toString())
        .toList(),
    percent: json['percent'] as int? ?? 0,
    assumptions: (json['assumptions'] as List? ?? const [])
        .map((e) => e.toString())
        .toList(),
  );

  final List<String> missing;
  final int percent;
  final List<String> assumptions;

  bool get isReady => missing.isEmpty;
}
