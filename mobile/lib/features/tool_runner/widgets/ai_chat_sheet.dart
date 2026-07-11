import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../../core/error/api_exception.dart';
import '../../../data/repositories/ai_assist_repository.dart';
import '../../../data/services/workspace_service.dart';

/// رسالة محادثة بسيطة.
class _ChatMessage {
  const _ChatMessage({required this.role, required this.content});

  final String role; // user | assistant
  final String content;
}

/// ورقة المستشار الذكي: محادثة سياقية مرتبطة بالأداة والمشروع الحاليين.
class AiChatSheet extends StatefulWidget {
  const AiChatSheet({
    super.key,
    this.toolKey,
    this.projectPublicId,
  });

  final String? toolKey;
  final String? projectPublicId;

  @override
  State<AiChatSheet> createState() => _AiChatSheetState();
}

class _AiChatSheetState extends State<AiChatSheet> {
  final _input = TextEditingController();
  final _scroll = ScrollController();
  final _messages = <_ChatMessage>[];
  bool _sending = false;
  String? _error;

  @override
  void dispose() {
    _input.dispose();
    _scroll.dispose();
    super.dispose();
  }

  Future<void> _send() async {
    final text = _input.text.trim();
    if (text.isEmpty || _sending) return;

    setState(() {
      _messages.add(_ChatMessage(role: 'user', content: text));
      _sending = true;
      _error = null;
      _input.clear();
    });
    _scrollToEnd();

    final ws = Get.find<WorkspaceService>().activeId;
    if (ws == null) {
      setState(() {
        _sending = false;
        _error = 'لا توجد مساحة عمل نشطة.';
      });
      return;
    }

    try {
      final reply = await Get.find<AiAssistRepository>().chat(
        ws,
        messages: _messages
            .map((m) => {'role': m.role, 'content': m.content})
            .toList(),
        toolKey: widget.toolKey,
        projectPublicId: widget.projectPublicId,
      );
      setState(() {
        _messages.add(_ChatMessage(role: 'assistant', content: reply));
      });
      _scrollToEnd();
    } on ApiException catch (e) {
      setState(() {
        _error = e.isCreditsExhausted
            ? 'انتهى رصيد المساعد الذكي. رقِّ باقتك للمتابعة.'
            : e.message;
      });
    } finally {
      setState(() => _sending = false);
    }
  }

  void _scrollToEnd() {
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (_scroll.hasClients) {
        _scroll.animateTo(
          _scroll.position.maxScrollExtent,
          duration: const Duration(milliseconds: 250),
          curve: Curves.easeOut,
        );
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return DraggableScrollableSheet(
      expand: false,
      initialChildSize: 0.85,
      minChildSize: 0.5,
      builder: (context, scrollController) => Padding(
        padding: EdgeInsets.only(
          bottom: MediaQuery.of(context).viewInsets.bottom,
        ),
        child: Column(
          children: [
            Padding(
              padding: const EdgeInsets.all(16),
              child: Row(
                children: [
                  Icon(Icons.support_agent, color: theme.colorScheme.primary),
                  const SizedBox(width: 8),
                  Text('المستشار الذكي',
                      style: theme.textTheme.titleMedium
                          ?.copyWith(fontWeight: FontWeight.w800)),
                  const Spacer(),
                  IconButton(
                    icon: const Icon(Icons.close),
                    onPressed: () => Get.back(),
                  ),
                ],
              ),
            ),
            const Divider(height: 1),
            Expanded(
              child: _messages.isEmpty
                  ? Center(
                      child: Padding(
                        padding: const EdgeInsets.all(24),
                        child: Text(
                          'اسأل عن أي شيء يخص هذه الأداة أو مشروعك.',
                          style: theme.textTheme.bodyMedium,
                          textAlign: TextAlign.center,
                        ),
                      ),
                    )
                  : ListView.builder(
                      controller: _scroll,
                      padding: const EdgeInsets.all(16),
                      itemCount: _messages.length,
                      itemBuilder: (_, i) => _Bubble(message: _messages[i]),
                    ),
            ),
            if (_error != null)
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
                child: Text(_error!,
                    style: TextStyle(color: theme.colorScheme.error)),
              ),
            SafeArea(
              child: Padding(
                padding: const EdgeInsets.fromLTRB(16, 8, 16, 12),
                child: Row(
                  children: [
                    Expanded(
                      child: TextField(
                        controller: _input,
                        onSubmitted: (_) => _send(),
                        textInputAction: TextInputAction.send,
                        decoration: const InputDecoration(
                          hintText: 'اكتب سؤالك...',
                        ),
                      ),
                    ),
                    const SizedBox(width: 8),
                    IconButton.filled(
                      onPressed: _sending ? null : _send,
                      icon: _sending
                          ? const SizedBox(
                              height: 18,
                              width: 18,
                              child: CircularProgressIndicator(strokeWidth: 2.2))
                          : const Icon(Icons.send),
                    ),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _Bubble extends StatelessWidget {
  const _Bubble({required this.message});

  final _ChatMessage message;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final isUser = message.role == 'user';
    return Align(
      alignment: isUser
          ? AlignmentDirectional.centerEnd
          : AlignmentDirectional.centerStart,
      child: Container(
        margin: const EdgeInsets.only(bottom: 10),
        padding: const EdgeInsets.all(12),
        constraints: BoxConstraints(
          maxWidth: MediaQuery.of(context).size.width * 0.78,
        ),
        decoration: BoxDecoration(
          color: isUser
              ? theme.colorScheme.primary
              : theme.colorScheme.surfaceContainerHighest,
          borderRadius: BorderRadius.circular(14),
        ),
        child: SelectableText(
          message.content,
          style: theme.textTheme.bodyMedium?.copyWith(
            color: isUser ? theme.colorScheme.onPrimary : null,
            height: 1.6,
          ),
        ),
      ),
    );
  }
}
