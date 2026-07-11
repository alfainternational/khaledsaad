/// عميل الوكالة (مختصر) — يعكس ClientResource.
class ClientRef {
  const ClientRef({required this.publicId, required this.name});

  final String publicId;
  final String name;

  factory ClientRef.fromJson(Map<String, dynamic> json) => ClientRef(
    publicId: json['public_id']?.toString() ?? '',
    name: json['name']?.toString() ?? '',
  );
}

/// نموذج المشروع — يعكس ProjectResource / ProjectDetailResource.
class ProjectModel {
  const ProjectModel({
    required this.publicId,
    required this.name,
    required this.stage,
    required this.status,
    this.sector,
    this.marketCountry,
    this.primaryDomain,
    this.logoUrl,
    this.monitoringEnabled = false,
    this.client,
    // حقول تفصيلية (من ProjectDetailResource)
    this.officialSocialLinks = const [],
    this.competitors = const [],
    this.analysisGoals = const [],
    this.briefAssessment,
    this.journeySnapshot,
    this.readiness,
    this.latestAudit,
  });

  final String publicId;
  final String name;
  final int stage;
  final String status;
  final String? sector;
  final String? marketCountry;
  final String? primaryDomain;
  final String? logoUrl;
  final bool monitoringEnabled;
  final ClientRef? client;

  final List<dynamic> officialSocialLinks;
  final List<dynamic> competitors;
  final List<dynamic> analysisGoals;
  final Map<String, dynamic>? briefAssessment;
  final Map<String, dynamic>? journeySnapshot;
  final Map<String, dynamic>? readiness;
  final Map<String, dynamic>? latestAudit;

  factory ProjectModel.fromJson(Map<String, dynamic> json) {
    return ProjectModel(
      publicId: json['public_id']?.toString() ?? '',
      name: json['name']?.toString() ?? '',
      stage: (json['stage'] is num) ? (json['stage'] as num).toInt() : 1,
      status: json['status']?.toString() ?? 'active',
      sector: json['sector']?.toString(),
      marketCountry: json['market_country']?.toString(),
      primaryDomain: json['primary_domain']?.toString(),
      logoUrl: json['logo_url']?.toString(),
      monitoringEnabled: json['monitoring_enabled'] == true,
      client: json['client'] is Map
          ? ClientRef.fromJson(Map<String, dynamic>.from(json['client'] as Map))
          : null,
      officialSocialLinks:
          (json['official_social_links'] as List?)?.toList() ?? const [],
      competitors: (json['competitors'] as List?)?.toList() ?? const [],
      analysisGoals: (json['analysis_goals'] as List?)?.toList() ?? const [],
      briefAssessment: _asMap(json['brief_assessment']),
      journeySnapshot: _asMap(json['journey_snapshot']),
      readiness: _asMap(json['readiness']),
      latestAudit: _asMap(json['latest_audit']),
    );
  }

  static Map<String, dynamic>? _asMap(dynamic v) =>
      v is Map ? Map<String, dynamic>.from(v) : null;
}
