import '../../core/config/api_endpoints.dart';
import '../../core/network/api_client.dart';
import '../models/workspace_model.dart';

class WorkspaceRepository {
  WorkspaceRepository(this._api);

  final ApiClient _api;

  Future<List<WorkspaceModel>> list() async {
    final res = await _api.get(ApiEndpoints.workspaces);
    final rows = (res['data'] as List?) ?? const [];
    return rows
        .map((e) => WorkspaceModel.fromJson(Map<String, dynamic>.from(e as Map)))
        .toList();
  }
}
