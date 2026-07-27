class RecommendationModel {
  const RecommendationModel({
    required this.id,
    required this.title,
    required this.description,
    required this.impactLabel,
    required this.effortLabel,
    this.kpiHint,
    this.taskId,
  });

  factory RecommendationModel.fromJson(Map<String, dynamic> json) =>
      RecommendationModel(
        id: json['id'] as int,
        title: json['title'] as String,
        description: json['description'] as String,
        impactLabel: json['impact_label'] as String? ?? '',
        effortLabel: json['effort_label'] as String? ?? '',
        kpiHint: json['kpi_hint'] as String?,
        taskId: json['task_id'] as int?,
      );

  final int id;
  final String title;
  final String description;
  final String impactLabel;
  final String effortLabel;
  final String? kpiHint;
  final int? taskId;

  bool get isTask => taskId != null;
}

class FindingModel {
  const FindingModel({
    required this.id,
    required this.title,
    required this.description,
    required this.category,
    required this.severity,
    required this.severityLabel,
    required this.isAssumption,
    required this.basisLabel,
    required this.confidence,
    required this.recommendations,
    this.evidence,
  });

  factory FindingModel.fromJson(Map<String, dynamic> json) => FindingModel(
    id: json['id'] as int,
    title: json['title'] as String,
    description: json['description'] as String,
    category: json['category'] as String? ?? '',
    severity: json['severity'] as String? ?? 'medium',
    severityLabel: json['severity_label'] as String? ?? '',
    isAssumption: json['is_assumption'] as bool? ?? false,
    basisLabel: json['basis_label'] as String? ?? '',
    confidence: json['confidence'] as int? ?? 0,
    evidence: json['evidence'] as String?,
    recommendations: (json['recommendations'] as List? ?? const [])
        .map(
          (e) =>
              RecommendationModel.fromJson(Map<String, dynamic>.from(e as Map)),
        )
        .toList(),
  );

  final int id;
  final String title;
  final String description;
  final String category;
  final String severity;
  final String severityLabel;
  final bool isAssumption;
  final String basisLabel;
  final int confidence;
  final String? evidence;
  final List<RecommendationModel> recommendations;
}

class ReportSectionModel {
  const ReportSectionModel({
    required this.key,
    required this.title,
    required this.content,
  });

  factory ReportSectionModel.fromJson(Map<String, dynamic> json) =>
      ReportSectionModel(
        key: json['key'] as String,
        title: json['title'] as String,
        content: Map<String, dynamic>.from(json['content'] as Map? ?? const {}),
      );

  final String key;
  final String title;
  final Map<String, dynamic> content;

  String? get headline => content['headline'] as String?;

  List<Map<String, dynamic>> get points =>
      (content['points'] as List? ?? const [])
          .map((e) => Map<String, dynamic>.from(e as Map))
          .toList();

  List<Map<String, dynamic>> get breakdown =>
      (content['breakdown'] as List? ?? const [])
          .map((e) => Map<String, dynamic>.from(e as Map))
          .toList();
}

/// عنصر رسم بياني بسيط: تسمية + عدد + لون سداسي قادم من الخادم.
class ChartItemModel {
  const ChartItemModel({
    required this.key,
    required this.label,
    required this.count,
    required this.colorHex,
  });

  factory ChartItemModel.fromJson(Map<String, dynamic> json) => ChartItemModel(
    key: json['key'] as String? ?? '',
    label: json['label'] as String? ?? '',
    count: json['count'] as int? ?? 0,
    colorHex: json['color'] as String? ?? '#2575ff',
  );

  final String key;
  final String label;
  final int count;
  final String colorHex;
}

class ChartSeriesModel {
  const ChartSeriesModel({
    required this.title,
    required this.items,
    required this.total,
  });

  factory ChartSeriesModel.fromJson(Map<String, dynamic> json) =>
      ChartSeriesModel(
        title: json['title'] as String? ?? '',
        total: json['total'] as int? ?? 0,
        items: (json['items'] as List? ?? const [])
            .map(
              (e) =>
                  ChartItemModel.fromJson(Map<String, dynamic>.from(e as Map)),
            )
            .toList(),
      );

  final String title;
  final List<ChartItemModel> items;
  final int total;
}

class ScoreGaugeModel {
  const ScoreGaugeModel({
    required this.title,
    required this.value,
    required this.max,
    required this.band,
    required this.colorHex,
  });

  factory ScoreGaugeModel.fromJson(Map<String, dynamic> json) =>
      ScoreGaugeModel(
        title: json['title'] as String? ?? '',
        value: json['value'] as int? ?? 0,
        max: json['max'] as int? ?? 100,
        band: json['band'] as String? ?? '',
        colorHex: json['color'] as String? ?? '#2575ff',
      );

  final String title;
  final int value;
  final int max;
  final String band;
  final String colorHex;
}

class ScoreHistoryPointModel {
  const ScoreHistoryPointModel({
    required this.label,
    required this.value,
    required this.isCurrent,
  });

  factory ScoreHistoryPointModel.fromJson(Map<String, dynamic> json) =>
      ScoreHistoryPointModel(
        label: json['label'] as String? ?? '',
        value: json['value'] as int? ?? 0,
        isCurrent: json['is_current'] as bool? ?? false,
      );

  final String label;
  final int value;
  final bool isCurrent;
}

class ScoreHistoryModel {
  const ScoreHistoryModel({
    required this.title,
    required this.points,
    required this.max,
  });

  factory ScoreHistoryModel.fromJson(Map<String, dynamic> json) =>
      ScoreHistoryModel(
        title: json['title'] as String? ?? '',
        max: json['max'] as int? ?? 100,
        points: (json['points'] as List? ?? const [])
            .map(
              (e) => ScoreHistoryPointModel.fromJson(
                Map<String, dynamic>.from(e as Map),
              ),
            )
            .toList(),
      );

  final String title;
  final List<ScoreHistoryPointModel> points;
  final int max;
}

class ImpactEffortModel {
  const ImpactEffortModel({
    required this.title,
    required this.impactLabels,
    required this.effortLabels,
    required this.cells,
    required this.quickWins,
  });

  factory ImpactEffortModel.fromJson(Map<String, dynamic> json) =>
      ImpactEffortModel(
        title: json['title'] as String? ?? '',
        impactLabels: Map<String, String>.from(
          (json['impact_labels'] as Map? ?? const {}).map(
            (k, v) => MapEntry(k.toString(), v.toString()),
          ),
        ),
        effortLabels: Map<String, String>.from(
          (json['effort_labels'] as Map? ?? const {}).map(
            (k, v) => MapEntry(k.toString(), v.toString()),
          ),
        ),
        cells: (json['cells'] as List? ?? const [])
            .map((e) => Map<String, dynamic>.from(e as Map))
            .toList(),
        quickWins: json['quick_wins'] as int? ?? 0,
      );

  final String title;
  final Map<String, String> impactLabels;
  final Map<String, String> effortLabels;
  final List<Map<String, dynamic>> cells;
  final int quickWins;

  int countFor(String impact, String effort) => cells
      .where((cell) => cell['impact'] == impact && cell['effort'] == effort)
      .fold(0, (sum, cell) => sum + (cell['count'] as int? ?? 0));
}

/// حزمة رسوم التقرير كما يرسلها الخادم — نفس مصدر الويب والـPDF.
class ReportChartsModel {
  const ReportChartsModel({
    this.gauge,
    this.history,
    this.severity,
    this.evidence,
    this.impactEffort,
  });

  factory ReportChartsModel.fromJson(Map<String, dynamic> json) =>
      ReportChartsModel(
        gauge: json['score_gauge'] is Map
            ? ScoreGaugeModel.fromJson(
                Map<String, dynamic>.from(json['score_gauge'] as Map),
              )
            : null,
        history: json['score_history'] is Map
            ? ScoreHistoryModel.fromJson(
                Map<String, dynamic>.from(json['score_history'] as Map),
              )
            : null,
        severity: json['severity_distribution'] is Map
            ? ChartSeriesModel.fromJson(
                Map<String, dynamic>.from(json['severity_distribution'] as Map),
              )
            : null,
        evidence: json['evidence_split'] is Map
            ? ChartSeriesModel.fromJson(
                Map<String, dynamic>.from(json['evidence_split'] as Map),
              )
            : null,
        impactEffort: json['impact_effort'] is Map
            ? ImpactEffortModel.fromJson(
                Map<String, dynamic>.from(json['impact_effort'] as Map),
              )
            : null,
      );

  final ScoreGaugeModel? gauge;
  final ScoreHistoryModel? history;
  final ChartSeriesModel? severity;
  final ChartSeriesModel? evidence;
  final ImpactEffortModel? impactEffort;

  bool get isEmpty =>
      gauge == null &&
      history == null &&
      severity == null &&
      evidence == null &&
      impactEffort == null;
}

class ReportComparisonModel {
  const ReportComparisonModel({
    required this.delta,
    required this.direction,
    required this.label,
  });

  factory ReportComparisonModel.fromJson(Map<String, dynamic> json) =>
      ReportComparisonModel(
        delta: json['delta'] as int? ?? 0,
        direction: json['direction'] as String? ?? 'flat',
        label: json['label'] as String? ?? '',
      );

  final int delta;
  final String direction;
  final String label;
}

class ReportWatcherModel {
  const ReportWatcherModel({
    required this.status,
    required this.changes,
    this.lastCheckedAt,
    this.lastChangedAt,
  });

  factory ReportWatcherModel.fromJson(Map<String, dynamic> json) =>
      ReportWatcherModel(
        status: json['status'] as String? ?? 'paused',
        changes: (json['changes'] as List? ?? const [])
            .whereType<Map>()
            .map((change) => Map<String, dynamic>.from(change))
            .toList(),
        lastCheckedAt: json['last_checked_at'] as String?,
        lastChangedAt: json['last_changed_at'] as String?,
      );

  final String status;
  final List<Map<String, dynamic>> changes;
  final String? lastCheckedAt;
  final String? lastChangedAt;

  bool get isActive => status == 'active';
}

class NextToolSuggestionModel {
  const NextToolSuggestionModel({
    required this.toolKey,
    required this.toolTitle,
    required this.reason,
  });

  factory NextToolSuggestionModel.fromJson(Map<String, dynamic> json) {
    final tool = Map<String, dynamic>.from(json['tool'] as Map? ?? const {});

    return NextToolSuggestionModel(
      toolKey: tool['key'] as String? ?? '',
      toolTitle: tool['title'] as String? ?? '',
      reason: json['reason'] as String? ?? '',
    );
  }

  final String toolKey;
  final String toolTitle;
  final String reason;
}

class ReportDetail {
  const ReportDetail({
    required this.id,
    required this.title,
    required this.score,
    required this.scoreBand,
    required this.summary,
    required this.assumptions,
    required this.sections,
    required this.findings,
    required this.evidenceBacked,
    required this.assumptionCount,
    required this.toolTitle,
    required this.projectName,
    required this.projectSlug,
    required this.isManuallyReviewed,
    this.nextStepTitle,
    this.nextStepDescription,
    this.reviewedAt,
    this.toolVersion,
    this.charts,
    this.comparison,
    this.watcher,
    this.myVerdict,
    this.suggestion,
  });

  factory ReportDetail.fromJson(Map<String, dynamic> json) {
    final nextStep = json['next_step'] as Map?;
    final counts = Map<String, dynamic>.from(
      json['counts'] as Map? ?? const {},
    );
    final provenance = Map<String, dynamic>.from(
      json['provenance'] as Map? ?? const {},
    );

    return ReportDetail(
      id: json['id'] as int,
      title: json['title'] as String,
      score: json['score'] as int? ?? 0,
      scoreBand: json['score_band'] as String? ?? '',
      summary: json['summary'] as String? ?? '',
      isManuallyReviewed: json['is_manually_reviewed'] as bool? ?? false,
      reviewedAt: json['reviewed_at'] as String?,
      assumptions: (json['assumptions'] as List? ?? const [])
          .map((e) => e.toString())
          .toList(),
      nextStepTitle: nextStep?['title']?.toString(),
      nextStepDescription: nextStep?['description']?.toString(),
      toolVersion: provenance['tool_version'] as int?,
      toolTitle: (json['tool'] as Map?)?['title']?.toString() ?? '',
      projectName: (json['project'] as Map?)?['name']?.toString() ?? '',
      projectSlug: (json['project'] as Map?)?['slug']?.toString() ?? '',
      evidenceBacked: counts['evidence_backed'] as int? ?? 0,
      assumptionCount: counts['assumptions'] as int? ?? 0,
      sections: (json['sections'] as List? ?? const [])
          .map(
            (e) => ReportSectionModel.fromJson(
              Map<String, dynamic>.from(e as Map),
            ),
          )
          .toList(),
      findings: (json['findings'] as List? ?? const [])
          .map(
            (e) => FindingModel.fromJson(Map<String, dynamic>.from(e as Map)),
          )
          .toList(),
      charts: json['charts'] is Map
          ? ReportChartsModel.fromJson(
              Map<String, dynamic>.from(json['charts'] as Map),
            )
          : null,
      comparison: json['comparison'] is Map
          ? ReportComparisonModel.fromJson(
              Map<String, dynamic>.from(json['comparison'] as Map),
            )
          : null,
      watcher: json['watcher'] is Map
          ? ReportWatcherModel.fromJson(
              Map<String, dynamic>.from(json['watcher'] as Map),
            )
          : null,
      myVerdict: json['my_verdict'] as String?,
      suggestion: json['suggestion'] is Map
          ? NextToolSuggestionModel.fromJson(
              Map<String, dynamic>.from(json['suggestion'] as Map),
            )
          : null,
    );
  }

  final int id;
  final String title;
  final int score;
  final String scoreBand;
  final String summary;
  final List<String> assumptions;
  final List<ReportSectionModel> sections;
  final List<FindingModel> findings;
  final int evidenceBacked;
  final int assumptionCount;
  final String toolTitle;
  final String projectName;
  final String projectSlug;
  final bool isManuallyReviewed;
  final String? nextStepTitle;
  final String? nextStepDescription;
  final String? reviewedAt;
  final int? toolVersion;
  final ReportChartsModel? charts;
  final ReportComparisonModel? comparison;
  final ReportWatcherModel? watcher;
  final String? myVerdict;
  final NextToolSuggestionModel? suggestion;
}
