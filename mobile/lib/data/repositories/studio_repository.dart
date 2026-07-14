import '../../core/config/api_endpoints.dart';
import '../../core/network/api_client.dart';
import '../../core/utils/idempotency.dart';
import '../models/studio_models.dart';

class StudioRepository {
  StudioRepository(this._api);

  final ApiClient _api;

  Future<List<AiTemplate>> templates(String ws) async {
    final res = await _api.get(ApiEndpoints.studioTemplates(ws));
    final rows = (res['data'] as List?) ?? const [];
    return rows
        .map((e) => AiTemplate.fromJson(Map<String, dynamic>.from(e as Map)))
        .toList();
  }

  Future<List<StudioGeneration>> generations(String ws) async {
    final res = await _api.get(ApiEndpoints.studioGenerations(ws));
    final rows = (res['data'] as List?) ?? const [];
    return rows
        .map((e) => StudioGeneration.fromJson(Map<String, dynamic>.from(e as Map)))
        .toList();
  }

  Future<StudioGeneration> generate(
    String ws, {
    required int templateId,
    String? projectId,
    String? brief,
    bool freshCopy = false,
  }) async {
    final res = await _api.postGenerative(
      ApiEndpoints.studioGenerations(ws),
      // مهلة العميل تتجاوز ميزانية الخادم (180ث) كي لا يُقطع الاتصال بينما
      // اكتمل التوليد فعلاً على الخادم (توليد يتيم + رصيد مخصوم).
      receiveTimeout: const Duration(seconds: 190),
      idempotencyKey: stableIdempotencyKey('studio', [
        templateId,
        projectId ?? '',
        brief ?? '',
        // نسخة جديدة مقصودة → مفتاح فريد يتجاوز كاش الـ idempotency (نافذة 24س)
        // فيولّد الخادم مخرجاً جديداً بدل إعادة المخزّن.
        if (freshCopy) DateTime.now().microsecondsSinceEpoch,
      ]),
      body: {
        'template_id': templateId,
        'project_public_id': ?projectId,
        'brief': ?brief,
      },
    );
    return StudioGeneration.fromJson(Map<String, dynamic>.from(res['data'] as Map));
  }

  Future<StudioGeneration> show(String ws, String generationPublicId) async {
    final res = await _api.get(ApiEndpoints.studioGeneration(ws, generationPublicId));
    return StudioGeneration.fromJson(Map<String, dynamic>.from(res['data'] as Map));
  }

  Future<void> delete(String ws, String generationPublicId) =>
      _api.delete(ApiEndpoints.studioGeneration(ws, generationPublicId));

  /// تنزيل ملف تصدير كبايتات (md/html/pdf) مع مصادقة Bearer.
  Future<List<int>> export(
    String ws,
    String generationPublicId,
    String format,
  ) =>
      _api.download(
        ApiEndpoints.studioGenerationExport(ws, generationPublicId, format),
      );
}
