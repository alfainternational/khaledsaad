import '../../core/config/api_endpoints.dart';
import '../../core/network/api_client.dart';
import '../models/collab_models.dart';
import '../models/dashboard_models.dart';

/// التعاون والحساب: الداشبورد، onboarding، الحساب، الفريق، الموافقات، العملاء، العلامة.
class CollabRepository {
  CollabRepository(this._api);

  final ApiClient _api;

  // ---- الداشبورد ----

  Future<DashboardSnapshot> dashboard(String ws) async {
    final res = await _api.get(ApiEndpoints.dashboard(ws));
    return DashboardSnapshot.fromJson(_asMap(res['data']));
  }

  // ---- onboarding ----

  Future<Map<String, dynamic>> onboarding(String ws) async {
    final res = await _api.get(ApiEndpoints.onboarding(ws));
    return _asMap(res['data']);
  }

  Future<void> completeOnboarding(String ws, Map<String, dynamic> payload) =>
      _api.post(ApiEndpoints.onboarding(ws), body: payload);

  // ---- الحساب ----

  Future<AccountOverview> account(String ws) async {
    final res = await _api.get(ApiEndpoints.account(ws));
    return AccountOverview.fromJson(_asMap(res['data']));
  }

  Future<void> updateAccount(String ws, Map<String, dynamic> payload) =>
      _api.patch(ApiEndpoints.account(ws), body: payload);

  // ---- مفتاح الذكاء الخاص بالحساب (BYOK) ----

  /// حالة الربط: {connected, provider, masked_key, available_providers}.
  Future<Map<String, dynamic>> aiKeyStatus(String ws) async {
    final res = await _api.get(ApiEndpoints.accountAiKey(ws));
    return _asMap(res['data']);
  }

  /// يربط مفتاح المستخدم الخاص. يعيد الحالة المحدّثة (المفتاح مقنّع).
  Future<Map<String, dynamic>> setAiKey(
    String ws, {
    required String provider,
    required String key,
  }) async {
    final res = await _api.put(
      ApiEndpoints.accountAiKey(ws),
      body: {'provider': provider, 'key': key},
    );
    return _asMap(res['data']);
  }

  /// يلغي الربط ويعيد التوليد لرصيد المنصة.
  Future<void> clearAiKey(String ws) => _api.delete(ApiEndpoints.accountAiKey(ws));

  // ---- الفريق ----

  Future<({List<TeamMember> members, List<TeamInvitation> invitations})> team(
      String ws) async {
    final res = await _api.get(ApiEndpoints.team(ws));
    final data = _asMap(res['data']);
    return (
      members: ((data['members'] as List?) ?? const [])
          .map((e) => TeamMember.fromJson(Map<String, dynamic>.from(e as Map)))
          .toList(),
      invitations: ((data['invitations'] as List?) ?? const [])
          .map((e) =>
              TeamInvitation.fromJson(Map<String, dynamic>.from(e as Map)))
          .toList(),
    );
  }

  Future<void> invite(String ws,
          {required String email, required String role}) =>
      _api.post(ApiEndpoints.teamInvitations(ws),
          body: {'email': email, 'role': role});

  Future<void> removeMember(String ws, int memberId) =>
      _api.delete('/workspaces/$ws/team/members/$memberId');

  Future<void> removeInvitation(String ws, int invitationId) =>
      _api.delete('/workspaces/$ws/team/invitations/$invitationId');

  Future<Map<String, dynamic>> acceptInvitation(String token) async {
    final res = await _api.post('/team/invitations/$token/accept');
    return _asMap(res['data']);
  }

  // ---- الموافقات ----

  Future<({List<ApprovalModel> approvals, Map<String, dynamic> meta})>
      approvals(String ws, {String? status}) async {
    final res = await _api.get(
      '/workspaces/$ws/approvals',
      query: status != null && status.isNotEmpty ? {'status': status} : null,
    );
    return (
      approvals: ((res['data'] as List?) ?? const [])
          .map((e) =>
              ApprovalModel.fromJson(Map<String, dynamic>.from(e as Map)))
          .toList(),
      meta: _asMap(res['meta']),
    );
  }

  Future<void> reviewApproval(String ws, int approvalId,
          {required String status, String? note}) =>
      _api.patch('/workspaces/$ws/approvals/$approvalId', body: {
        'status': status,
        'note': ?note,
      });

  Future<void> requestApproval(
    String ws,
    String project, {
    required String itemType,
    required String itemPublicId,
    String? note,
  }) =>
      _api.post('/workspaces/$ws/projects/$project/approvals', body: {
        'item_type': itemType,
        'item_public_id': itemPublicId,
        'note': ?note,
      });

  // ---- عملاء الوكالة ----

  Future<List<AgencyClient>> clients(String ws) async {
    final res = await _api.get('/workspaces/$ws/clients');
    return ((res['data'] as List?) ?? const [])
        .map((e) => AgencyClient.fromJson(Map<String, dynamic>.from(e as Map)))
        .toList();
  }

  Future<AgencyClient> createClient(String ws, Map<String, dynamic> payload) async {
    final res = await _api.post('/workspaces/$ws/clients', body: payload);
    return AgencyClient.fromJson(Map<String, dynamic>.from(res['data'] as Map));
  }

  Future<AgencyClient> updateClient(
      String ws, String client, Map<String, dynamic> payload) async {
    final res = await _api.put('/workspaces/$ws/clients/$client', body: payload);
    return AgencyClient.fromJson(Map<String, dynamic>.from(res['data'] as Map));
  }

  Future<void> deleteClient(String ws, String client) =>
      _api.delete('/workspaces/$ws/clients/$client');

  // ---- علامة الوكالة ----

  Future<Map<String, dynamic>> branding(String ws) async {
    final res = await _api.get('/workspaces/$ws/agency/branding');
    return _asMap(res['data']);
  }

  Future<Map<String, dynamic>> updateBranding(
      String ws, Map<String, dynamic> payload) async {
    final res =
        await _api.patch('/workspaces/$ws/agency/branding', body: payload);
    return _asMap(res['data']);
  }

  static Map<String, dynamic> _asMap(dynamic v) =>
      v is Map ? Map<String, dynamic>.from(v) : <String, dynamic>{};
}
