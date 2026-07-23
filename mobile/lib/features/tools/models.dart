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
  String get headline => (promise != null && promise!.isNotEmpty) ? promise! : description;
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
        inputs: (json['inputs'] as List? ?? const []).map((e) => e.toString()).toList(),
        outputs: (json['outputs'] as List? ?? const []).map((e) => e.toString()).toList(),
      );

  final ToolCard card;
  final int stepCount;
  final List<String> inputs;
  final List<String> outputs;
}

class FieldOption {
  const FieldOption({required this.value, required this.label});

  factory FieldOption.fromJson(Map<String, dynamic> json) =>
      FieldOption(value: json['value'].toString(), label: json['label'].toString());

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
    this.value,
  });

  factory ToolFieldModel.fromJson(Map<String, dynamic> json) => ToolFieldModel(
        key: json['key'] as String,
        label: json['label'] as String,
        type: json['type'] as String,
        required: json['required'] as bool? ?? true,
        help: json['help'] as String?,
        options: (json['options'] as List? ?? const [])
            .map((e) => FieldOption.fromJson(Map<String, dynamic>.from(e as Map)))
            .toList(),
        value: json['value'],
      );

  final String key;
  final String label;
  final String type;
  final bool required;
  final String? help;
  final List<FieldOption> options;
  final dynamic value;

  List<String> get selectedValues => value is List
      ? (value as List).map((e) => e.toString()).toList()
      : const <String>[];

  String get textValue => value == null ? '' : value.toString();
}

class WizardStep {
  const WizardStep({required this.step, required this.title, required this.fields});

  factory WizardStep.fromJson(Map<String, dynamic> json) => WizardStep(
        step: json['step'] as int,
        title: json['title'] as String,
        fields: (json['fields'] as List)
            .map((e) => ToolFieldModel.fromJson(Map<String, dynamic>.from(e as Map)))
            .toList(),
      );

  final int step;
  final String title;
  final List<ToolFieldModel> fields;
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
    required this.steps,
    required this.stages,
    this.baseScore,
    this.reportId,
    this.failureReason,
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
        toolTitle: (json['tool'] as Map?)?['title']?.toString() ?? '',
        projectName: (json['project'] as Map?)?['name']?.toString() ?? '',
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
  final String toolTitle;
  final String projectName;
  final List<WizardStep> steps;
  final List<RunStage> stages;
}

class Preflight {
  const Preflight({required this.missing, required this.percent, required this.assumptions});

  factory Preflight.fromJson(Map<String, dynamic> json) => Preflight(
        missing: (json['missing'] as List? ?? const []).map((e) => e.toString()).toList(),
        percent: json['percent'] as int? ?? 0,
        assumptions: (json['assumptions'] as List? ?? const []).map((e) => e.toString()).toList(),
      );

  final List<String> missing;
  final int percent;
  final List<String> assumptions;

  bool get isReady => missing.isEmpty;
}
