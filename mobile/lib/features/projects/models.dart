class ProjectCard {
  const ProjectCard({
    required this.slug,
    required this.name,
    this.industry,
    this.latestScore,
    this.scoreBand,
  });

  factory ProjectCard.fromJson(Map<String, dynamic> json) => ProjectCard(
    slug: json['slug'] as String,
    name: json['name'] as String,
    industry: json['industry'] as String?,
    latestScore: json['latest_score'] as int?,
    scoreBand: json['score_band'] as String?,
  );

  final String slug;
  final String name;
  final String? industry;
  final int? latestScore;
  final String? scoreBand;
}

class ScoreComparison {
  const ScoreComparison({
    required this.delta,
    required this.direction,
    required this.label,
  });

  factory ScoreComparison.fromJson(Map<String, dynamic> json) =>
      ScoreComparison(
        delta: json['delta'] as int? ?? 0,
        direction: json['direction'] as String? ?? 'flat',
        label: json['label'] as String? ?? '',
      );

  final int delta;
  final String direction;
  final String label;
}

class ReportCard {
  const ReportCard({
    required this.id,
    required this.title,
    required this.score,
    required this.scoreBand,
    this.createdAt,
  });

  factory ReportCard.fromJson(Map<String, dynamic> json) => ReportCard(
    id: json['id'] as int,
    title: json['title'] as String,
    score: json['score'] as int? ?? 0,
    scoreBand: json['score_band'] as String? ?? '',
    createdAt: json['created_at'] as String?,
  );

  final int id;
  final String title;
  final int score;
  final String scoreBand;
  final String? createdAt;
}

class KpiModel {
  const KpiModel({
    required this.id,
    required this.name,
    this.unit,
    this.latest,
    this.attainmentPercent,
  });

  factory KpiModel.fromJson(Map<String, dynamic> json) => KpiModel(
    id: json['id'] as int,
    name: json['name'] as String,
    unit: json['unit'] as String?,
    latest: (json['latest'] as num?)?.toDouble(),
    attainmentPercent: json['attainment_percent'] as int?,
  );

  final int id;
  final String name;
  final String? unit;
  final double? latest;
  final int? attainmentPercent;
}

class ProjectOverview {
  const ProjectOverview({
    required this.card,
    required this.reports,
    required this.kpis,
    required this.openTasks,
    required this.overdueTasks,
    required this.doneTasks,
    this.latestReport,
    this.comparison,
  });

  factory ProjectOverview.fromJson(Map<String, dynamic> json) {
    final tasks = Map<String, dynamic>.from(json['tasks'] as Map? ?? const {});

    return ProjectOverview(
      card: ProjectCard.fromJson(json),
      latestReport: json['latest_report'] == null
          ? null
          : ReportCard.fromJson(
              Map<String, dynamic>.from(json['latest_report'] as Map),
            ),
      comparison: json['comparison'] == null
          ? null
          : ScoreComparison.fromJson(
              Map<String, dynamic>.from(json['comparison'] as Map),
            ),
      reports: (json['reports'] as List? ?? const [])
          .map((e) => ReportCard.fromJson(Map<String, dynamic>.from(e as Map)))
          .toList(),
      kpis: (json['kpis'] as List? ?? const [])
          .map((e) => KpiModel.fromJson(Map<String, dynamic>.from(e as Map)))
          .toList(),
      openTasks: tasks['open'] as int? ?? 0,
      overdueTasks: tasks['overdue'] as int? ?? 0,
      doneTasks: tasks['done'] as int? ?? 0,
    );
  }

  final ProjectCard card;
  final ReportCard? latestReport;
  final ScoreComparison? comparison;
  final List<ReportCard> reports;
  final List<KpiModel> kpis;
  final int openTasks;
  final int overdueTasks;
  final int doneTasks;
}

class TaskModel {
  const TaskModel({
    required this.id,
    required this.title,
    required this.status,
    required this.statusLabel,
    required this.isOverdue,
    this.description,
    this.dueDate,
    this.impact,
    this.effort,
  });

  factory TaskModel.fromJson(Map<String, dynamic> json) => TaskModel(
    id: json['id'] as int,
    title: json['title'] as String,
    status: json['status'] as String,
    statusLabel: json['status_label'] as String,
    isOverdue: json['is_overdue'] as bool? ?? false,
    description: json['description'] as String?,
    dueDate: json['due_date'] as String?,
    impact: json['impact'] as String?,
    effort: json['effort'] as String?,
  );

  final int id;
  final String title;
  final String status;
  final String statusLabel;
  final bool isOverdue;
  final String? description;
  final String? dueDate;
  final String? impact;
  final String? effort;
}
