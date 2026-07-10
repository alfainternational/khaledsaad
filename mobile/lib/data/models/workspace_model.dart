/// نموذج مساحة العمل — يعكس WorkspaceSummaryResource / WorkspaceResource.
class WorkspaceModel {
  const WorkspaceModel({
    required this.publicId,
    required this.name,
    required this.type,
    required this.status,
    this.role,
    this.entitlements = const {},
  });

  final String publicId;
  final String name;
  final String type;
  final String status;
  final String? role;

  /// خريطة الصلاحيات المفعّلة {key: value} (عند توفّرها).
  final Map<String, dynamic> entitlements;

  bool get isAgency => type == 'agency';

  /// هل الصلاحية مفعّلة؟ (قيمة صادقة).
  bool hasEntitlement(String key) {
    final value = entitlements[key];
    if (value is bool) return value;
    if (value is num) return value > 0;
    return value != null && value.toString().isNotEmpty && value.toString() != 'false';
  }

  factory WorkspaceModel.fromJson(Map<String, dynamic> json) {
    return WorkspaceModel(
      publicId: json['public_id']?.toString() ?? '',
      name: json['name']?.toString() ?? '',
      type: json['type']?.toString() ?? 'personal',
      status: json['status']?.toString() ?? 'active',
      role: json['role']?.toString(),
      entitlements: json['entitlements'] is Map
          ? Map<String, dynamic>.from(json['entitlements'] as Map)
          : const {},
    );
  }
}
