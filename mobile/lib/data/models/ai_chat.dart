class AiChatConversation {
  const AiChatConversation({
    required this.publicId,
    required this.title,
    required this.toolKey,
    this.projectName,
  });

  factory AiChatConversation.fromJson(Map<String, dynamic> json) {
    final project = json['project'];
    return AiChatConversation(
      publicId: json['public_id']?.toString() ?? '',
      title: json['title']?.toString() ?? 'محادثة',
      toolKey: json['tool_key']?.toString() ?? 'general',
      projectName: project is Map ? project['name']?.toString() : null,
    );
  }

  final String publicId;
  final String title;
  final String toolKey;
  final String? projectName;
}

class AiChatMessage {
  const AiChatMessage({
    required this.publicId,
    required this.role,
    required this.status,
    this.content,
    this.errorMessage,
  });

  factory AiChatMessage.fromJson(Map<String, dynamic> json) => AiChatMessage(
    publicId: json['public_id']?.toString() ?? '',
    role: json['role']?.toString() ?? 'assistant',
    status: json['status']?.toString() ?? 'queued',
    content: json['content']?.toString(),
    errorMessage: json['error_message']?.toString(),
  );

  final String publicId;
  final String role;
  final String status;
  final String? content;
  final String? errorMessage;

  bool get isPending => !const {'completed', 'failed'}.contains(status);
}

class AiChatConversationPage {
  const AiChatConversationPage({
    required this.items,
    required this.currentPage,
    required this.lastPage,
  });

  factory AiChatConversationPage.fromJson(Map<String, dynamic> json) {
    final raw = json['data'];
    final meta = json['meta'];
    return AiChatConversationPage(
      items: raw is List
          ? raw
                .whereType<Map>()
                .map(
                  (item) => AiChatConversation.fromJson(
                    Map<String, dynamic>.from(item),
                  ),
                )
                .toList()
          : const [],
      currentPage: meta is Map
          ? (meta['current_page'] as num?)?.toInt() ?? 1
          : 1,
      lastPage: meta is Map ? (meta['last_page'] as num?)?.toInt() ?? 1 : 1,
    );
  }

  final List<AiChatConversation> items;
  final int currentPage;
  final int lastPage;
}

class AiChatThread {
  const AiChatThread({
    required this.conversation,
    required this.messages,
    required this.currentPage,
    required this.lastPage,
  });

  factory AiChatThread.fromJson(Map<String, dynamic> json) {
    final conversation = json['data'];
    final messages = json['messages'];
    final rawMessages = messages is Map ? messages['data'] : null;
    final meta = messages is Map ? messages['meta'] : null;
    return AiChatThread(
      conversation: AiChatConversation.fromJson(
        conversation is Map
            ? Map<String, dynamic>.from(conversation)
            : const {},
      ),
      messages: rawMessages is List
          ? rawMessages
                .whereType<Map>()
                .map(
                  (item) =>
                      AiChatMessage.fromJson(Map<String, dynamic>.from(item)),
                )
                .toList()
          : const [],
      currentPage: meta is Map
          ? (meta['current_page'] as num?)?.toInt() ?? 1
          : 1,
      lastPage: meta is Map ? (meta['last_page'] as num?)?.toInt() ?? 1 : 1,
    );
  }

  final AiChatConversation conversation;
  final List<AiChatMessage> messages;
  final int currentPage;
  final int lastPage;
}

class AiChatSendResult {
  const AiChatSendResult({
    required this.conversation,
    required this.userMessage,
    required this.assistantMessage,
  });

  factory AiChatSendResult.fromJson(Map<String, dynamic> json) {
    final data = json['data'] is Map
        ? Map<String, dynamic>.from(json['data'] as Map)
        : <String, dynamic>{};
    return AiChatSendResult(
      conversation: AiChatConversation.fromJson(
        Map<String, dynamic>.from(data['conversation'] as Map? ?? const {}),
      ),
      userMessage: AiChatMessage.fromJson(
        Map<String, dynamic>.from(data['user_message'] as Map? ?? const {}),
      ),
      assistantMessage: AiChatMessage.fromJson(
        Map<String, dynamic>.from(
          data['assistant_message'] as Map? ?? const {},
        ),
      ),
    );
  }

  final AiChatConversation conversation;
  final AiChatMessage userMessage;
  final AiChatMessage assistantMessage;
}
