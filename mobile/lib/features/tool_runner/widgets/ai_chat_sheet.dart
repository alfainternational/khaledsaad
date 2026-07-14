import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:uuid/uuid.dart';

import '../../../core/error/api_exception.dart';
import '../../../data/models/ai_chat.dart';
import '../../../data/repositories/ai_assist_repository.dart';
import '../../../data/services/workspace_service.dart';

class AiChatSheet extends StatefulWidget {
  const AiChatSheet({super.key, this.toolKey, this.projectPublicId});

  final String? toolKey;
  final String? projectPublicId;

  @override
  State<AiChatSheet> createState() => _AiChatSheetState();
}

class _AiChatSheetState extends State<AiChatSheet> {
  final _input = TextEditingController();

  /// وحدة التمرير التي يمرّرها DraggableScrollableSheet — استخدامها ضروري
  /// كي تعمل إيماءة سحب الورقة مع تمرير القائمة معاً.
  ScrollController? _listScroll;
  final _uuid = const Uuid();
  final _messages = <AiChatMessage>[];
  final _conversations = <AiChatConversation>[];
  /// نص رد المساعد أثناء البثّ الحيّ (null = لا بثّ جارٍ).
  String? _streamingText;
  AiChatConversation? _conversation;
  bool _loading = true;
  bool _sending = false;
  bool _showHistory = false;
  int _messagePage = 1;
  int _messageLastPage = 1;
  int _historyPage = 1;
  int _historyLastPage = 1;
  String? _error;

  AiAssistRepository get _repository => Get.find<AiAssistRepository>();
  String? get _workspaceId => Get.find<WorkspaceService>().activeId;

  @override
  void initState() {
    super.initState();
    _initialize();
  }

  @override
  void dispose() {
    _input.dispose();
    // _listScroll مملوك من DraggableScrollableSheet — لا نتخلّص منه هنا.
    super.dispose();
  }

  Future<void> _initialize() async {
    final ws = _workspaceId;
    if (ws == null) {
      _setError('لا توجد مساحة عمل نشطة.');
      return;
    }

    try {
      final page = await _repository.conversations(ws);
      _conversations
        ..clear()
        ..addAll(page.items);
      _historyPage = page.currentPage;
      _historyLastPage = page.lastPage;
      if (page.items.isEmpty) {
        await _createConversation();
      } else {
        await _openConversation(page.items.first);
      }
    } on ApiException catch (error) {
      _setError(error.message);
    }
  }

  Future<void> _createConversation() async {
    final ws = _workspaceId;
    if (ws == null) return;
    setState(() {
      _loading = true;
      _showHistory = false;
      _error = null;
    });

    try {
      final conversation = await _repository.createConversation(
        ws,
        toolKey: widget.toolKey,
        projectPublicId: widget.projectPublicId,
      );
      if (!mounted) return;
      setState(() {
        _conversation = conversation;
        _messages.clear();
        _messagePage = 1;
        _messageLastPage = 1;
        _conversations.insert(0, conversation);
        _loading = false;
      });
    } on ApiException catch (error) {
      _setError(error.message);
    }
  }

  Future<void> _openConversation(
    AiChatConversation conversation, {
    int page = 1,
    bool prepend = false,
  }) async {
    final ws = _workspaceId;
    if (ws == null) return;
    if (!prepend) {
      setState(() {
        _loading = true;
        _showHistory = false;
        _error = null;
      });
    }

    try {
      final thread = await _repository.conversation(
        ws,
        conversation.publicId,
        page: page,
      );
      if (!mounted) return;
      setState(() {
        _conversation = thread.conversation;
        if (prepend) {
          _messages.insertAll(0, thread.messages);
        } else {
          _messages
            ..clear()
            ..addAll(thread.messages);
        }
        _messagePage = thread.currentPage;
        _messageLastPage = thread.lastPage;
        _loading = false;
      });
      if (!prepend) _scrollToEnd();
    } on ApiException catch (error) {
      _setError(error.message);
    }
  }

  Future<void> _loadMoreHistory() async {
    final ws = _workspaceId;
    if (ws == null || _historyPage >= _historyLastPage) return;
    try {
      final page = await _repository.conversations(ws, page: _historyPage + 1);
      if (!mounted) return;
      setState(() {
        _conversations.addAll(page.items);
        _historyPage = page.currentPage;
        _historyLastPage = page.lastPage;
      });
    } on ApiException catch (error) {
      _setError(error.message);
    }
  }

  Future<void> _send() async {
    final text = _input.text.trim();
    final ws = _workspaceId;
    if (text.isEmpty || _sending || ws == null) return;
    if (_conversation == null) await _createConversation();
    final conversation = _conversation;
    if (conversation == null) return;

    final userMsg = AiChatMessage(
      publicId: _uuid.v4(),
      role: 'user',
      status: 'completed',
      content: text,
    );
    setState(() {
      _sending = true;
      _error = null;
      _input.clear();
      _messages.add(userMsg);
      _streamingText = '';
    });
    _scrollToEnd();

    // تاريخ المحادثة كمصفوفة {role, content} لتغذية البثّ.
    final history = _messages
        .where((m) => (m.content?.trim().isNotEmpty ?? false))
        .map((m) => {'role': m.role, 'content': m.content!.trim()})
        .toList();

    try {
      await for (final delta in _repository.chatStream(
        ws,
        messages: history,
        toolKey: widget.toolKey,
        projectPublicId: widget.projectPublicId,
      )) {
        if (!mounted) return;
        setState(() => _streamingText = (_streamingText ?? '') + delta);
        _scrollToEnd();
      }
      if (!mounted) return;
      final reply = (_streamingText ?? '').trim();
      setState(() {
        if (reply.isNotEmpty) {
          _messages.add(AiChatMessage(
            publicId: _uuid.v4(),
            role: 'assistant',
            status: 'completed',
            content: reply,
          ));
        }
        _streamingText = null;
      });
      _scrollToEnd();
    } on ApiException catch (error) {
      if (mounted) {
        setState(() {
          _streamingText = null;
          _error = error.message;
        });
      }
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  void _setError(String message) {
    if (!mounted) return;
    setState(() {
      _loading = false;
      _sending = false;
      _error = message;
    });
  }

  void _scrollToEnd() {
    WidgetsBinding.instance.addPostFrameCallback((_) {
      final scroll = _listScroll;
      if (scroll != null && scroll.hasClients) {
        scroll.animateTo(
          scroll.position.maxScrollExtent,
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
      builder: (context, scrollController) {
        _listScroll = scrollController;
        return Padding(
        padding: EdgeInsets.only(
          bottom: MediaQuery.of(context).viewInsets.bottom,
        ),
        child: Column(
          children: [
            const _SheetDragHandle(),
            Padding(
              padding: const EdgeInsets.all(12),
              child: Row(
                children: [
                  Icon(Icons.support_agent, color: theme.colorScheme.primary),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      _conversation?.title ?? 'المستشار الذكي',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: theme.textTheme.titleMedium?.copyWith(
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ),
                  IconButton(
                    tooltip: 'المحادثات السابقة',
                    icon: const Icon(Icons.history),
                    onPressed: () => setState(() => _showHistory = true),
                  ),
                  IconButton(
                    tooltip: 'محادثة جديدة',
                    icon: const Icon(Icons.add_comment_outlined),
                    onPressed: _createConversation,
                  ),
                  IconButton(
                    tooltip: 'إغلاق',
                    icon: const Icon(Icons.close),
                    onPressed: () => Get.back(),
                  ),
                ],
              ),
            ),
            const Divider(height: 1),
            Expanded(
              child: _showHistory
                  ? _HistoryList(
                      scrollController: scrollController,
                      conversations: _conversations,
                      canLoadMore: _historyPage < _historyLastPage,
                      onSelected: _openConversation,
                      onLoadMore: _loadMoreHistory,
                    )
                  : _conversationBody(theme, scrollController),
            ),
            if (_error != null)
              Padding(
                padding: const EdgeInsets.symmetric(
                  horizontal: 16,
                  vertical: 4,
                ),
                child: Text(
                  _error!,
                  style: TextStyle(color: theme.colorScheme.error),
                ),
              ),
            if (!_showHistory) _composer(),
          ],
        ),
      );
      },
    );
  }

  Widget _conversationBody(ThemeData theme, ScrollController scrollController) {
    if (_loading) {
      return ListView(
        controller: scrollController,
        children: const [
          SizedBox(height: 120),
          Center(child: CircularProgressIndicator()),
        ],
      );
    }
    if (_messages.isEmpty) {
      return ListView(
        controller: scrollController,
        children: [
          const SizedBox(height: 80),
          Padding(
            padding: const EdgeInsets.all(24),
            child: Text(
              'اسأل عن أي شيء يخص هذه الأداة أو مشروعك.',
              style: theme.textTheme.bodyMedium,
              textAlign: TextAlign.center,
            ),
          ),
        ],
      );
    }

    final canLoadOlder = _messagePage < _messageLastPage;
    final leading = canLoadOlder ? 1 : 0;
    final streaming = _streamingText != null;
    return ListView.builder(
      controller: scrollController,
      padding: const EdgeInsets.all(16),
      itemCount: leading + _messages.length + (streaming ? 1 : 0),
      itemBuilder: (_, index) {
        if (canLoadOlder && index == 0) {
          return TextButton(
            onPressed: () => _openConversation(
              _conversation!,
              page: _messagePage + 1,
              prepend: true,
            ),
            child: const Text('عرض رسائل أقدم'),
          );
        }
        final messageIndex = index - leading;
        // فقاعة الرد الحيّ أثناء البثّ (تظهر رمزاً برمز).
        if (streaming && messageIndex == _messages.length) {
          final live = _streamingText ?? '';
          return _Bubble(
            message: AiChatMessage(
              publicId: '_streaming',
              role: 'assistant',
              status: 'queued',
              content: live.isEmpty ? '…' : live,
            ),
          );
        }
        return _Bubble(message: _messages[messageIndex]);
      },
    );
  }

  Widget _composer() => SafeArea(
    child: Padding(
      padding: const EdgeInsets.fromLTRB(16, 8, 16, 12),
      child: Row(
        children: [
          Expanded(
            child: TextField(
              controller: _input,
              onSubmitted: (_) => _send(),
              textInputAction: TextInputAction.send,
              decoration: const InputDecoration(hintText: 'اكتب سؤالك...'),
            ),
          ),
          const SizedBox(width: 8),
          IconButton.filled(
            onPressed: _sending ? null : _send,
            icon: _sending
                ? const SizedBox(
                    height: 18,
                    width: 18,
                    child: CircularProgressIndicator(strokeWidth: 2.2),
                  )
                : const Icon(Icons.send),
          ),
        ],
      ),
    ),
  );
}

class _HistoryList extends StatelessWidget {
  const _HistoryList({
    required this.scrollController,
    required this.conversations,
    required this.canLoadMore,
    required this.onSelected,
    required this.onLoadMore,
  });

  final ScrollController scrollController;
  final List<AiChatConversation> conversations;
  final bool canLoadMore;
  final ValueChanged<AiChatConversation> onSelected;
  final VoidCallback onLoadMore;

  @override
  Widget build(BuildContext context) {
    if (conversations.isEmpty) {
      return ListView(
        controller: scrollController,
        children: const [
          SizedBox(height: 120),
          Center(child: Text('لا توجد محادثات سابقة.')),
        ],
      );
    }
    return ListView.builder(
      controller: scrollController,
      itemCount: conversations.length + (canLoadMore ? 1 : 0),
      itemBuilder: (_, index) {
        if (index == conversations.length) {
          return TextButton(
            onPressed: onLoadMore,
            child: const Text('عرض محادثات أقدم'),
          );
        }
        final conversation = conversations[index];
        return ListTile(
          title: Text(
            conversation.title,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
          ),
          subtitle: Text(conversation.projectName ?? 'محادثة عامة'),
          trailing: const Icon(Icons.chevron_left),
          onTap: () => onSelected(conversation),
        );
      },
    );
  }
}

/// مقبض سحب علوي موحّد للأوراق السفلية.
class _SheetDragHandle extends StatelessWidget {
  const _SheetDragHandle();

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(top: 8, bottom: 4),
      width: 40,
      height: 4,
      decoration: BoxDecoration(
        color: Theme.of(context).colorScheme.outlineVariant,
        borderRadius: BorderRadius.circular(999),
      ),
    );
  }
}

class _Bubble extends StatelessWidget {
  const _Bubble({required this.message});

  final AiChatMessage message;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final isUser = message.role == 'user';
    final text = message.status == 'failed'
        ? message.errorMessage ?? 'تعذر إكمال الرد.'
        : message.isPending
        ? 'يفكر...'
        : message.content ?? '';
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
          borderRadius: BorderRadius.circular(8),
        ),
        child: message.isPending
            ? Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  const SizedBox(
                    width: 14,
                    height: 14,
                    child: CircularProgressIndicator(strokeWidth: 2),
                  ),
                  const SizedBox(width: 8),
                  Text(text),
                ],
              )
            : SelectableText(
                text,
                style: theme.textTheme.bodyMedium?.copyWith(
                  color: isUser ? theme.colorScheme.onPrimary : null,
                  height: 1.6,
                ),
              ),
      ),
    );
  }
}
