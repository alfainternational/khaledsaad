import '../../core/config/api_endpoints.dart';
import '../../core/network/api_client.dart';
import '../models/ai_chat.dart';

/// مساعد الذكاء: محادثة، تحليل مدخلات الأداة، اقتراح حقول، وبحث حيّ.
class AiAssistRepository {
  AiAssistRepository(this._api);

  final ApiClient _api;

  Future<AiChatConversationPage> conversations(
    String ws, {
    int page = 1,
  }) async {
    final res = await _api.get(
      ApiEndpoints.aiConversations(ws),
      query: {'page': page, 'per_page': 50},
    );
    return AiChatConversationPage.fromJson(res);
  }

  Future<AiChatConversation> createConversation(
    String ws, {
    String? toolKey,
    String? projectPublicId,
  }) async {
    final res = await _api.post(
      ApiEndpoints.aiConversations(ws),
      body: {'tool_key': ?toolKey, 'project_public_id': ?projectPublicId},
    );
    return AiChatConversation.fromJson(
      Map<String, dynamic>.from(res['data'] as Map? ?? const {}),
    );
  }

  Future<AiChatThread> conversation(
    String ws,
    String conversationId, {
    int page = 1,
  }) async {
    final res = await _api.get(
      ApiEndpoints.aiConversation(ws, conversationId),
      query: {'page': page, 'per_page': 50},
    );
    return AiChatThread.fromJson(res);
  }

  Future<AiChatSendResult> sendMessage(
    String ws,
    String conversationId, {
    required String content,
    required String clientRequestId,
  }) async {
    final res = await _api.postGenerative(
      ApiEndpoints.aiConversationMessages(ws, conversationId),
      receiveTimeout: const Duration(seconds: 120),
      body: {'content': content, 'client_request_id': clientRequestId},
    );
    return AiChatSendResult.fromJson(res);
  }

  Future<AiChatMessage> message(
    String ws,
    String conversationId,
    String messageId,
  ) async {
    final res = await _api.get(
      ApiEndpoints.aiConversationMessage(ws, conversationId, messageId),
    );
    return AiChatMessage.fromJson(
      Map<String, dynamic>.from(res['data'] as Map? ?? const {}),
    );
  }

  /// محادثة استشارية. messages = [{role, content}].
  Future<String> chat(
    String ws, {
    required List<Map<String, String>> messages,
    String? toolKey,
    String? projectPublicId,
  }) async {
    final res = await _api.postGenerative(
      ApiEndpoints.aiChat(ws),
      receiveTimeout: const Duration(seconds: 120),
      body: {
        'messages': messages,
        'tool_key': ?toolKey,
        'project_public_id': ?projectPublicId,
      },
    );
    return res['response']?.toString() ?? '';
  }

  /// بثّ رد المستشار رمزاً برمز (SSE). يطلق مقاطع النص لحظياً.
  Stream<String> chatStream(
    String ws, {
    required List<Map<String, String>> messages,
    String? toolKey,
    String? projectPublicId,
  }) {
    return _api.stream(
      ApiEndpoints.aiChatStream(ws),
      body: {
        'messages': messages,
        'tool_key': ?toolKey,
        'project_public_id': ?projectPublicId,
      },
    );
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
    final res = await _api.postGenerative(
      ApiEndpoints.aiAnalyze(ws),
      receiveTimeout: const Duration(seconds: 120),
      body: {
        'tool_code': toolCode,
        'tool_name': toolName,
        'inputs': inputs,
        'mode': ?mode,
        'project_public_id': ?projectPublicId,
        'enrich': enrich,
      },
    );
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
    final res = await _api.postGenerative(
      ApiEndpoints.aiSuggest(ws),
      receiveTimeout: const Duration(seconds: 120),
      body: {
        'tool_code': toolCode,
        'tool_name': toolName,
        'inputs': inputs,
        'mode': ?mode,
        'project_public_id': ?projectPublicId,
      },
    );
    return Map<String, dynamic>.from(res);
  }

  /// بحث ويب حيّ.
  Future<Map<String, dynamic>> research(
    String ws,
    String query, {
    int depth = 3,
  }) async {
    final res = await _api.postGenerative(
      ApiEndpoints.aiResearch(ws),
      body: {'query': query, 'depth': depth},
    );
    return res['research'] is Map
        ? Map<String, dynamic>.from(res['research'] as Map)
        : {};
  }
}
