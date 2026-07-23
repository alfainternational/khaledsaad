/// حالة تعامل المستخدم مع أداة، كما يحسبها ToolEngagement في الخادم.
/// التطبيق لا يعيد اشتقاقها حتى لا يفترق عن الويب في الحكم.
library;

class Engagement {
  const Engagement({
    required this.state,
    required this.label,
    required this.percent,
    required this.canRestart,
    required this.target,
    this.hint,
    this.runUuid,
    this.reportId,
    this.projectName,
  });

  factory Engagement.fromJson(Map<String, dynamic> json) => Engagement(
        state: json['state'] as String? ?? 'new',
        label: json['label'] as String? ?? 'ابدأ من هنا',
        hint: json['hint'] as String?,
        percent: json['percent'] as int? ?? 0,
        canRestart: json['can_restart'] as bool? ?? false,
        target: json['target'] as String? ?? 'tool',
        runUuid: json['run_uuid'] as String?,
        reportId: json['report_id'] as int?,
        projectName: (json['project'] as Map?)?['name']?.toString(),
      );

  static const Engagement fresh = Engagement(
    state: 'new',
    label: 'ابدأ من هنا',
    percent: 0,
    canRestart: false,
    target: 'tool',
  );

  final String state;
  final String label;
  final String? hint;
  final int percent;
  final bool canRestart;

  /// wizard / status / report / tool — تحدد الشاشة التي يُفتح إليها.
  final String target;
  final String? runUuid;
  final int? reportId;
  final String? projectName;

  bool get isStarted => state != 'new';

  bool get isDraft => state == 'draft';

  bool get isRunning => state == 'running';
}

/// بطاقة «أكمل ما بدأته».
class ResumeCard {
  const ResumeCard({
    required this.runUuid,
    required this.toolKey,
    required this.toolTitle,
    required this.state,
    required this.label,
    required this.percent,
    this.projectName,
    this.projectSlug,
    this.hint,
  });

  factory ResumeCard.fromJson(Map<String, dynamic> json) => ResumeCard(
        runUuid: json['run_uuid'] as String,
        toolKey: json['tool_key'] as String,
        toolTitle: json['tool_title'] as String,
        state: json['state'] as String,
        label: json['label'] as String,
        percent: json['percent'] as int? ?? 0,
        projectName: json['project_name'] as String?,
        projectSlug: json['project_slug'] as String?,
        hint: json['hint'] as String?,
      );

  final String runUuid;
  final String toolKey;
  final String toolTitle;
  final String state;
  final String label;
  final int percent;
  final String? projectName;
  final String? projectSlug;
  final String? hint;

  bool get isDraft => state == 'draft';
}
