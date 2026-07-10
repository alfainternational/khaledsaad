import '../../core/config/api_endpoints.dart';
import '../../core/network/api_client.dart';

/// مساعد الذكاء: محادثة، تحليل مدخلات الأداة، اقتراح حقول، وبحث حيّ.
class AiAssistRepository {
  AiAssistRepository(this._api);

  final ApiClient _api;

  /// محادثة استشارية. messages = [{role, content}].
  Future<String> chat(
    String ws, {
    required List<Map<String, String>> messages,
    String? toolKey,
    String? projectPublicId,
  }) async {
    final res = await _api.post(ApiEndpoints.aiChat(ws), body: {
      'messages': messages,
      'tool_key': ?toolKey,
      'project_public_id': ?projectPublicId,
    });
    return res['response']?.toString() ?? '';
  }

  /// تحليل جودة مدخلات أداة (تقييم محلي + إثراء اختياري بالـ LLM).
  Future<Map<String, dynamic>> analyzeInputs(
    String ws, {
    required String toolCode,
    required String toolName,
    required Map<String, dynamic> inputs,
    String? mode,
    String? projectPublicId,
    bool enrich = true,
  }) async {
    final res = await _api.post(ApiEndpoints.aiAnalyze(ws), body: {
      'tool_code': toolCode,
      'tool_name': toolName,
      'inputs': inputs,
      'mode': ?mode,
      'project_public_id': ?projectPublicId,
      'enrich': enrich,
    });
    return res['analysis'] is Map
        ? Map<String, dynamic>.from(res['analysis'] as Map)
        : {};
  }

  /// اقتراح قيم للحقول.
  Future<Map<String, dynamic>> suggestFields(
    String ws, {
    required String toolCode,
    required String toolName,
    required Map<String, dynamic> inputs,
    String? mode,
    String? projectPublicId,
  }) async {
    final res = await _api.post(ApiEndpoints.aiSuggest(ws), body: {
      'tool_code': toolCode,
      'tool_name': toolName,
      'inputs': inputs,
      'mode': ?mode,
      'project_public_id': ?projectPublicId,
    });
    return Map<String, dynamic>.from(res);
  }

  /// بحث ويب حيّ.
  Future<Map<String, dynamic>> research(String ws, String query, {int depth = 3}) async {
    final res = await _api.post(ApiEndpoints.aiResearch(ws), body: {
      'query': query,
      'depth': depth,
    });
    return res['research'] is Map
        ? Map<String, dynamic>.from(res['research'] as Map)
        : {};
  }
}
