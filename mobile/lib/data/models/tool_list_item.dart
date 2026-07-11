/// عنصر أداة في الفهرس — يعكس ToolResource.
class ToolListItem {
  const ToolListItem({
    required this.code,
    required this.name,
    this.description,
    this.module,
    this.stage,
    this.outputType,
    this.sortOrder,
    this.estimatedMinutes,
    this.unlocked = true,
    this.completedInCurrentProject = false,
    this.currentProjectRuns = 0,
    this.recommendedNow = false,
    this.dependsOn = const [],
  });

  final String code;
  final String name;
  final String? description;
  final String? module;
  final int? stage;
  final String? outputType;
  final int? sortOrder;
  final int? estimatedMinutes;
  final bool unlocked;
  final bool completedInCurrentProject;
  final int currentProjectRuns;
  final bool recommendedNow;
  final List<dynamic> dependsOn;

  factory ToolListItem.fromJson(Map<String, dynamic> json) {
    return ToolListItem(
      code: json['code']?.toString() ?? '',
      name: json['name']?.toString() ?? '',
      description: json['description']?.toString(),
      module: json['module']?.toString(),
      stage: (json['stage'] is num) ? (json['stage'] as num).toInt() : null,
      outputType: json['output_type']?.toString(),
      sortOrder: (json['sort_order'] is num)
          ? (json['sort_order'] as num).toInt()
          : null,
      estimatedMinutes: (json['estimated_minutes'] is num)
          ? (json['estimated_minutes'] as num).toInt()
          : null,
      unlocked: json['unlocked'] != false,
      completedInCurrentProject: json['completed_in_current_project'] == true,
      currentProjectRuns: (json['current_project_runs'] is num)
          ? (json['current_project_runs'] as num).toInt()
          : 0,
      recommendedNow: json['recommended_now'] == true,
      dependsOn: (json['depends_on'] as List?)?.toList() ?? const [],
    );
  }
}
