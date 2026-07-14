/// قالب استوديو — يعكس AiTemplateResource.
class AiTemplate {
  const AiTemplate({
    required this.id,
    required this.code,
    required this.name,
    this.description,
    this.module,
    this.creditCost,
  });

  final int id;
  final String code;
  final String name;
  final String? description;
  final String? module;
  final int? creditCost;

  factory AiTemplate.fromJson(Map<String, dynamic> json) {
    return AiTemplate(
      id: (json['id'] is num) ? (json['id'] as num).toInt() : 0,
      code: json['code']?.toString() ?? '',
      name: json['name']?.toString() ?? '',
      description: json['description']?.toString(),
      module: json['module']?.toString(),
      creditCost: (json['credit_cost'] is num)
          ? (json['credit_cost'] as num).toInt()
          : null,
    );
  }
}

/// توليد استوديو — يعكس StudioGenerationResource.
class StudioGeneration {
  const StudioGeneration({
    required this.publicId,
    required this.status,
    this.templateId,
    this.templateName,
    this.output,
    this.error,
    this.createdAt,
  });

  final String publicId;
  final String status;
  final int? templateId;
  final String? templateName;
  final String? output;
  final String? error;
  final String? createdAt;

  bool get isReady => output != null && output!.trim().isNotEmpty;
  bool get isFailed => status == 'failed' || (error != null && error!.isNotEmpty);

  /// ما زال يُولَّد على الخادم (طابور) — لا مخرج بعد ولم يفشل.
  bool get isProcessing => !isReady && !isFailed;

  factory StudioGeneration.fromJson(Map<String, dynamic> json) {
    final template = json['template'];
    return StudioGeneration(
      publicId: json['public_id']?.toString() ?? '',
      status: json['status']?.toString() ?? 'pending',
      templateId: (json['template_id'] is num)
          ? (json['template_id'] as num).toInt()
          : null,
      templateName: template is Map ? template['name']?.toString() : null,
      output: json['output']?.toString(),
      error: json['error']?.toString(),
      createdAt: json['created_at']?.toString(),
    );
  }
}
