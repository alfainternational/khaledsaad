import '../../core/config/api_endpoints.dart';
import '../../core/network/api_client.dart';
import '../models/project_model.dart';

class ProjectRepository {
  ProjectRepository(this._api);

  final ApiClient _api;

  Future<List<ProjectModel>> list(
    String ws, {
    String? status,
    int? stage,
    int page = 1,
  }) async {
    final res = await _api.get(
      ApiEndpoints.projects(ws),
      query: {
        'page': page,
        if (status != null && status.isNotEmpty) 'status': status,
        if (stage != null && stage > 0) 'stage': stage,
      },
    );
    final rows = (res['data'] as List?) ?? const [];
    return rows
        .map((e) => ProjectModel.fromJson(Map<String, dynamic>.from(e as Map)))
        .toList();
  }

  Future<ProjectModel> show(String ws, String project) async {
    final res = await _api.get(ApiEndpoints.project(ws, project));
    return ProjectModel.fromJson(Map<String, dynamic>.from(res['data'] as Map));
  }

  Future<ProjectModel> create(String ws, Map<String, dynamic> payload) async {
    final res = await _api.post(ApiEndpoints.projects(ws), body: payload);
    return ProjectModel.fromJson(Map<String, dynamic>.from(res['data'] as Map));
  }

  Future<ProjectModel> update(
    String ws,
    String project,
    Map<String, dynamic> payload,
  ) async {
    final res = await _api.put(ApiEndpoints.project(ws, project), body: payload);
    return ProjectModel.fromJson(Map<String, dynamic>.from(res['data'] as Map));
  }

  Future<void> delete(String ws, String project) =>
      _api.delete(ApiEndpoints.project(ws, project));
}
