import '../../core/config/api_endpoints.dart';
import '../../core/network/api_client.dart';
import '../models/lifecycle_models.dart';

/// دورة المشروع: brief، التدقيق الذكي، التوصيات، حزم التنفيذ، التقارير.
class LifecycleRepository {
  LifecycleRepository(this._api);

  final ApiClient _api;

  // ---- ملف المشروع (brief) ----

  Future<({Map<String, dynamic> brief, Map<String, dynamic> assessment})> brief(
      String ws, String project) async {
    final res = await _api.get(ApiEndpoints.projectBrief(ws, project));
    final data = Map<String, dynamic>.from(res['data'] as Map);
    return (
      brief: _asMap(data['brief']),
      assessment: _asMap(data['assessment']),
    );
  }

  Future<({Map<String, dynamic> brief, Map<String, dynamic> assessment})>
      updateBrief(String ws, String project, Map<String, dynamic> payload) async {
    final res =
        await _api.put(ApiEndpoints.projectBrief(ws, project), body: payload);
    final data = Map<String, dynamic>.from(res['data'] as Map);
    return (
      brief: _asMap(data['brief']),
      assessment: _asMap(data['assessment']),
    );
  }

  // ---- التدقيق الذكي (audit) ----

  Future<Map<String, dynamic>> runAudit(String ws, String project) async {
    final res = await _api.post(ApiEndpoints.projectAudit(ws, project));
    return _asMap(res['data']);
  }

  Future<Map<String, dynamic>> auditStatus(String ws, String project) async {
    final res = await _api.get(ApiEndpoints.projectAuditStatus(ws, project));
    return _asMap(res['data']);
  }

  // ---- التوصيات وحزم التنفيذ ----

  Future<List<RecommendationModel>> recommendations(
      String ws, String project) async {
    final res = await _api.get(ApiEndpoints.projectRecommendations(ws, project));
    final rows = (res['data'] as List?) ?? const [];
    return rows
        .map((e) =>
            RecommendationModel.fromJson(Map<String, dynamic>.from(e as Map)))
        .toList();
  }

  Future<ExecutionPackageModel> createPackage(
      String ws, String project, String recommendation) async {
    final res = await _api
        .post(ApiEndpoints.recommendationPackage(ws, project, recommendation));
    return ExecutionPackageModel.fromJson(
        Map<String, dynamic>.from(res['data'] as Map));
  }

  Future<ExecutionPackageModel> package(String ws, String pkg) async {
    final res = await _api.get(ApiEndpoints.executionPackage(ws, pkg));
    return ExecutionPackageModel.fromJson(
        Map<String, dynamic>.from(res['data'] as Map));
  }

  Future<ExecutionPackageModel> updatePackageStatus(
      String ws, String pkg, String status) async {
    final res = await _api.patch(
      ApiEndpoints.executionPackageStatus(ws, pkg),
      body: {'status': status},
    );
    return ExecutionPackageModel.fromJson(
        Map<String, dynamic>.from(res['data'] as Map));
  }

  /// تحديث حالة مهمة (start/complete/reopen ⇒ pending/in_progress/done).
  /// يعيد الحزمة كاملة محدّثة.
  Future<ExecutionPackageModel> updateTaskStatus(
      String ws, String task, String status) async {
    final res = await _api.patch(
      ApiEndpoints.executionTaskStatus(ws, task),
      body: {'status': status},
    );
    return ExecutionPackageModel.fromJson(
        Map<String, dynamic>.from(res['data'] as Map));
  }

  /// إضافة تقرير قياس للحزمة. يعيد الحزمة كاملة محدّثة.
  Future<ExecutionPackageModel> addReport(
    String ws,
    String pkg, {
    required String phase,
    required int progress,
    String? note,
    String? metricName,
    String? metricValue,
  }) async {
    final res = await _api.post(
      ApiEndpoints.executionPackageReports(ws, pkg),
      body: {
        'phase': phase,
        'progress': progress,
        if (note != null && note.trim().isNotEmpty) 'note': note.trim(),
        if (metricName != null && metricName.trim().isNotEmpty)
          'metric_name': metricName.trim(),
        if (metricValue != null && metricValue.trim().isNotEmpty)
          'metric_value': metricValue.trim(),
      },
    );
    return ExecutionPackageModel.fromJson(
        Map<String, dynamic>.from(res['data'] as Map));
  }

  // ---- التقارير ----

  Future<Map<String, dynamic>> report(String ws, String project,
      {bool fresh = false}) async {
    final res = await _api.get(
      ApiEndpoints.projectReport(ws, project),
      query: fresh ? {'fresh': 1} : null,
    );
    return _asMap(res['data']);
  }

  Future<Map<String, dynamic>> dossier(String ws, String project) async {
    final res = await _api.get(ApiEndpoints.projectDossier(ws, project));
    return _asMap(res['data']);
  }

  Future<List<int>> reportPdf(String ws, String project) =>
      _api.download(ApiEndpoints.projectReportPdf(ws, project));

  Future<List<int>> dossierPdf(String ws, String project) =>
      _api.download(ApiEndpoints.projectDossierPdf(ws, project));

  static Map<String, dynamic> _asMap(dynamic v) =>
      v is Map ? Map<String, dynamic>.from(v) : <String, dynamic>{};
}
