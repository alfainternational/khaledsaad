/// توصية — يعكس RecommendationResource.
class RecommendationModel {
  const RecommendationModel({
    required this.publicId,
    required this.title,
    this.area,
    this.priority,
    this.severity,
    this.evidence,
    this.rationale,
    this.estimatedImpact,
    this.status,
    this.packagePublicId,
  });

  final String publicId;
  final String title;
  final String? area;
  final int? priority;
  final String? severity;
  final String? evidence;
  final String? rationale;
  final String? estimatedImpact;
  final String? status;

  /// أول حزمة تنفيذ مرتبطة (إن وُجدت).
  final String? packagePublicId;

  factory RecommendationModel.fromJson(Map<String, dynamic> json) {
    final packages = json['execution_packages'];
    String? pkgId;
    if (packages is List && packages.isNotEmpty && packages.first is Map) {
      pkgId = (packages.first as Map)['public_id']?.toString();
    }
    return RecommendationModel(
      publicId: json['public_id']?.toString() ?? '',
      title: json['title']?.toString() ?? '',
      area: json['area']?.toString(),
      priority: (json['priority'] is num)
          ? (json['priority'] as num).toInt()
          : null,
      severity: json['severity']?.toString(),
      evidence: json['evidence']?.toString(),
      rationale: json['rationale']?.toString(),
      estimatedImpact: json['estimated_impact']?.toString(),
      status: json['status']?.toString(),
      packagePublicId: pkgId,
    );
  }
}

/// حزمة تنفيذ — يعكس ExecutionPackageResource.
class ExecutionPackageModel {
  const ExecutionPackageModel({
    required this.publicId,
    required this.title,
    required this.status,
    this.problem,
    this.evidence,
    this.decision,
    this.measurementPlan,
    this.deadline,
    this.tasks = const [],
    this.assets = const [],
  });

  final String publicId;
  final String title;
  final String status;
  final String? problem;
  final String? evidence;
  final String? decision;
  final String? measurementPlan;
  final String? deadline;
  final List<Map<String, dynamic>> tasks;
  final List<Map<String, dynamic>> assets;

  String get studioBrief {
    final lines = <String>[
      'حزمة التنفيذ: $title',
      if (_hasText(problem)) 'المشكلة: ${problem!.trim()}',
      if (_hasText(evidence)) 'الدليل: ${evidence!.trim()}',
      if (_hasText(decision)) 'القرار: ${decision!.trim()}',
      if (assets.isNotEmpty) ..._assetBriefLines(assets.first),
      if (_hasText(measurementPlan)) 'خطة القياس: ${measurementPlan!.trim()}',
    ];

    return lines.join('\n');
  }

  static const statuses = [
    'proposed',
    'in_review',
    'approved',
    'in_progress',
    'executed',
    'measuring',
  ];

  static const statusLabels = <String, String>{
    'proposed': 'مقترحة',
    'in_review': 'قيد المراجعة',
    'approved': 'معتمدة',
    'in_progress': 'قيد التنفيذ',
    'executed': 'منفّذة',
    'measuring': 'قياس النتائج',
  };

  factory ExecutionPackageModel.fromJson(Map<String, dynamic> json) {
    return ExecutionPackageModel(
      publicId: json['public_id']?.toString() ?? '',
      title: json['title']?.toString() ?? '',
      status: json['status']?.toString() ?? 'proposed',
      problem: json['problem']?.toString(),
      evidence: json['evidence']?.toString(),
      decision: json['decision']?.toString(),
      measurementPlan: json['measurement_plan']?.toString(),
      deadline: json['deadline']?.toString(),
      tasks: _listOfMaps(json['tasks']),
      assets: _listOfMaps(json['assets']),
    );
  }

  static List<Map<String, dynamic>> _listOfMaps(dynamic v) {
    if (v is! List) return const [];
    return v.whereType<Map>().map((e) => Map<String, dynamic>.from(e)).toList();
  }

  static bool _hasText(String? value) =>
      value != null && value.trim().isNotEmpty;

  static List<String> _assetBriefLines(Map<String, dynamic> asset) {
    final title = asset['title']?.toString().trim();
    final type = asset['type']?.toString().trim();
    final body = asset['body']?.toString().trim();

    return [
      if (_hasText(title)) 'الأصل المطلوب: $title',
      if (_hasText(type)) 'نوع الأصل: $type',
      if (_hasText(body)) 'المحتوى الأولي: $body',
    ];
  }
}
