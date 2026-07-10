/// عضو فريق — يعكس TeamMemberResource.
class TeamMember {
  const TeamMember({
    required this.id,
    required this.role,
    required this.status,
    this.name,
    this.email,
  });

  final int id;
  final String role;
  final String status;
  final String? name;
  final String? email;

  static const roleLabels = <String, String>{
    'owner': 'مالك',
    'admin': 'مشرف',
    'editor': 'محرر',
    'contributor': 'مساهم',
    'viewer': 'مشاهد',
    'client': 'عميل',
  };

  factory TeamMember.fromJson(Map<String, dynamic> json) {
    final user = json['user'];
    return TeamMember(
      id: (json['id'] as num?)?.toInt() ?? 0,
      role: json['role']?.toString() ?? 'viewer',
      status: json['status']?.toString() ?? 'active',
      name: user is Map ? user['name']?.toString() : null,
      email: user is Map ? user['email']?.toString() : null,
    );
  }
}

/// دعوة فريق — يعكس InvitationResource.
class TeamInvitation {
  const TeamInvitation({
    required this.id,
    required this.email,
    required this.role,
    required this.status,
  });

  final int id;
  final String email;
  final String role;
  final String status;

  factory TeamInvitation.fromJson(Map<String, dynamic> json) => TeamInvitation(
        id: (json['id'] as num?)?.toInt() ?? 0,
        email: json['email']?.toString() ?? '',
        role: json['role']?.toString() ?? '',
        status: json['status']?.toString() ?? 'pending',
      );
}

/// موافقة — يعكس ApprovalResource.
class ApprovalModel {
  const ApprovalModel({
    required this.id,
    required this.itemType,
    required this.status,
    this.note,
    this.projectName,
    this.reviewerName,
    this.createdAt,
  });

  final int id;
  final String itemType;
  final String status;
  final String? note;
  final String? projectName;
  final String? reviewerName;
  final String? createdAt;

  static const itemTypeLabels = <String, String>{
    'tool_run': 'نتيجة أداة',
    'ai_generation': 'مخرج استوديو',
    'workspace_data': 'بيانات مساحة',
  };

  factory ApprovalModel.fromJson(Map<String, dynamic> json) {
    final project = json['project'];
    final reviewer = json['reviewer'];
    return ApprovalModel(
      id: (json['id'] as num?)?.toInt() ?? 0,
      itemType: json['item_type']?.toString() ?? '',
      status: json['status']?.toString() ?? 'pending',
      note: json['note']?.toString(),
      projectName: project is Map ? project['name']?.toString() : null,
      reviewerName: reviewer is Map ? reviewer['name']?.toString() : null,
      createdAt: json['created_at']?.toString(),
    );
  }
}

/// عميل وكالة — يعكس ClientResource الكامل.
class AgencyClient {
  const AgencyClient({
    required this.publicId,
    required this.name,
    this.status,
    this.email,
    this.phone,
    this.company,
    this.notes,
    this.projectsCount,
  });

  final String publicId;
  final String name;
  final String? status;
  final String? email;
  final String? phone;
  final String? company;
  final String? notes;
  final int? projectsCount;

  static const statusLabels = <String, String>{
    'active': 'نشط',
    'lead': 'محتمل',
    'inactive': 'غير نشط',
    'archived': 'مؤرشف',
  };

  factory AgencyClient.fromJson(Map<String, dynamic> json) {
    final contact = json['contact_info'];
    return AgencyClient(
      publicId: json['public_id']?.toString() ?? '',
      name: json['name']?.toString() ?? '',
      status: json['status']?.toString(),
      email: contact is Map ? contact['email']?.toString() : null,
      phone: contact is Map ? contact['phone']?.toString() : null,
      company: contact is Map ? contact['company']?.toString() : null,
      notes: contact is Map ? contact['notes']?.toString() : null,
      projectsCount: (json['projects_count'] as num?)?.toInt(),
    );
  }
}
