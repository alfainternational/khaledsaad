/// نتيجة تشغيل أداة — يعكس رد ToolRunApiController (run/load.data).
class ToolRunResult {
  const ToolRunResult({
    this.runPublicId,
    this.mode,
    this.completenessScore,
    this.aiGenerated = false,
    this.summary = const {},
    this.output = const {},
    this.inputs = const {},
    this.nextActions = const [],
    this.createdAt,
  });

  final String? runPublicId;
  final String? mode;
  final int? completenessScore;
  final bool aiGenerated;
  final Map<String, dynamic> summary;
  final Map<String, dynamic> output;
  final Map<String, dynamic> inputs;
  final List<dynamic> nextActions;
  final String? createdAt;

  bool get isEmpty => runPublicId == null && output.isEmpty && summary.isEmpty;

  factory ToolRunResult.fromJson(Map<String, dynamic> json) {
    return ToolRunResult(
      runPublicId: (json['run_public_id'] ?? json['public_id'])?.toString(),
      mode: json['mode']?.toString(),
      completenessScore: (json['completeness_score'] is num)
          ? (json['completeness_score'] as num).toInt()
          : null,
      aiGenerated: json['ai_generated'] == true ||
          (json['summary'] is Map && json['summary']['ai_generated'] == true),
      summary: _asMap(json['summary']),
      output: _asMap(json['output']),
      inputs: _asMap(json['inputs']),
      nextActions: (json['next_actions'] as List?)?.toList() ?? const [],
      createdAt: json['created_at']?.toString(),
    );
  }

  static Map<String, dynamic> _asMap(dynamic v) =>
      v is Map ? Map<String, dynamic>.from(v) : const {};
}
