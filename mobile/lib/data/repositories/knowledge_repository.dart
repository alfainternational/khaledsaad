import '../../core/config/api_endpoints.dart';
import '../../core/network/api_client.dart';
import '../models/knowledge_models.dart';

/// مصادر المعرفة: رفع مستندات المشروع التي يقرأها التحليل والذكاء.
class KnowledgeRepository {
  KnowledgeRepository(this._api);

  final ApiClient _api;

  Future<List<KnowledgeUpload>> list(String ws, String project) async {
    final res = await _api.get(ApiEndpoints.knowledgeUploads(ws, project));
    return ((res['data'] as List?) ?? const [])
        .map((e) => KnowledgeUpload.fromJson(Map<String, dynamic>.from(e as Map)))
        .toList();
  }

  Future<KnowledgeUpload> upload(
    String ws,
    String project, {
    required String filePath,
    required String filename,
  }) async {
    final res = await _api.upload(
      ApiEndpoints.knowledgeUploads(ws, project),
      filePath: filePath,
      filename: filename,
    );
    return KnowledgeUpload.fromJson(Map<String, dynamic>.from(res['data'] as Map));
  }

  Future<KnowledgeUpload> retry(String ws, String project, String upload) async {
    final res =
        await _api.post(ApiEndpoints.knowledgeUploadRetry(ws, project, upload));
    return KnowledgeUpload.fromJson(Map<String, dynamic>.from(res['data'] as Map));
  }

  Future<void> remove(String ws, String project, String upload) =>
      _api.delete(ApiEndpoints.knowledgeUpload(ws, project, upload));
}
