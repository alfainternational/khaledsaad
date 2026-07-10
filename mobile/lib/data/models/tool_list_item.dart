/// عنصر أداة في الفهرس — يعكس ToolResource.
class ToolListItem {
  const ToolListItem({
    required this.code,
    required this.name,
    this.description,
    this.module,
    this.stage,
    this.outputType,
    this.estimatedMinutes,
    this.dependsOn = const [],
  });

  final String code;
  final String name;
  final String? description;
  final String? module;
  final int? stage;
  final String? outputType;
  final int? estimatedMinutes;
  final List<dynamic> dependsOn;

  factory ToolListItem.fromJson(Map<String, dynamic> json) {
    return ToolListItem(
      code: json['code']?.toString() ?? '',
      name: json['name']?.toString() ?? '',
      description: json['description']?.toString(),
      module: json['module']?.toString(),
      stage: (json['stage'] is num) ? (json['stage'] as num).toInt() : null,
      outputType: json['output_type']?.toString(),
      estimatedMinutes: (json['estimated_minutes'] is num)
          ? (json['estimated_minutes'] as num).toInt()
          : null,
      dependsOn: (json['depends_on'] as List?)?.toList() ?? const [],
    );
  }
}
