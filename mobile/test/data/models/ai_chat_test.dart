import 'package:flutter_test/flutter_test.dart';
import 'package:ksgrowth_mobile/data/models/ai_chat.dart';

void main() {
  test('parses paginated private conversations and pending messages', () {
    final conversations = AiChatConversationPage.fromJson({
      'data': [
        {
          'public_id': 'conversation-1',
          'title': 'خطة النمو',
          'tool_key': 'general',
          'project': {'name': 'مشروعي'},
        },
      ],
      'meta': {'current_page': 1, 'last_page': 3},
    });
    final thread = AiChatThread.fromJson({
      'data': {
        'public_id': 'conversation-1',
        'title': 'خطة النمو',
        'tool_key': 'general',
      },
      'messages': {
        'data': [
          {
            'public_id': 'message-1',
            'role': 'assistant',
            'status': 'queued',
            'content': null,
          },
        ],
        'meta': {'current_page': 1, 'last_page': 2},
      },
    });

    expect(conversations.items.single.projectName, 'مشروعي');
    expect(conversations.lastPage, 3);
    expect(thread.messages.single.isPending, isTrue);
    expect(thread.lastPage, 2);
  });
}
