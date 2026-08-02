import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../core/api/api_exception.dart';
import '../../core/api/platform_repository.dart';
import '../../core/widgets/common.dart';

/// استوديو الرسائل على الجوال: الشخصية هي الشاشة.
///
/// تبويب أفقي لكل شخصية، وداخله ملفها وحده ورسالتها وحدها. لا جدول مقارنة
/// عريض ولا نتائج شخصيات أخرى داخل تبويب واحدة — العرض الضيق يُجبر على
/// الاختيار، والاختيار الصحيح هو صاحبة التبويب.
class MessageStudioScreen extends StatefulWidget {
  const MessageStudioScreen({
    super.key,
    required this.repository,
    required this.projectSlug,
    required this.projectName,
  });

  final PlatformRepository repository;
  final String projectSlug;
  final String projectName;

  @override
  State<MessageStudioScreen> createState() => _MessageStudioScreenState();
}

class _MessageStudioScreenState extends State<MessageStudioScreen> {
  late Future<Map<String, dynamic>> _studio;
  String _channel = 'ad';
  String _objective = 'attention';
  bool _busy = false;

  @override
  void initState() {
    super.initState();
    _reload();
  }

  void _reload() {
    _studio = widget.repository.messageStudio(widget.projectSlug);
  }

  Future<void> _run(
    Future<dynamic> Function() action, {
    required String success,
  }) async {
    setState(() => _busy = true);
    try {
      await action();
      if (!mounted) return;
      setState(_reload);
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(success)));
    } on ApiException catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(error.message)));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return FutureBuilder<Map<String, dynamic>>(
      future: _studio,
      builder: (context, snapshot) {
        if (snapshot.hasError) {
          return Scaffold(
            appBar: AppBar(title: const Text('استوديو الرسائل')),
            body: Center(child: Text('${snapshot.error}')),
          );
        }

        if (!snapshot.hasData) {
          return Scaffold(
            appBar: AppBar(title: const Text('استوديو الرسائل')),
            body: const Center(child: CircularProgressIndicator()),
          );
        }

        final data = snapshot.data!;
        final personas = List<Map<String, dynamic>>.from(
          (data['personas'] as List? ?? const [])
              .map((item) => Map<String, dynamic>.from(item as Map)),
        );

        if (personas.isEmpty) {
          return Scaffold(
            appBar: AppBar(title: const Text('استوديو الرسائل')),
            body: const Padding(
              padding: EdgeInsets.all(24),
              child: Text(
                'لوحة جمهورك لم تُبنَ بعد. ابنِها من مركز النمو، ثم عد لتكتب '
                'لكل شخصية رسالتها.',
              ),
            ),
          );
        }

        final channels = Map<String, dynamic>.from(
          data['channels'] as Map? ?? const {},
        );
        final objectives = Map<String, dynamic>.from(
          data['objectives'] as Map? ?? const {},
        );
        final limits = Map<String, dynamic>.from(
          data['limits'] as Map? ?? const {},
        );
        final results = _resultsByVariant(data);

        final evidence = Map<String, dynamic>.from(
          data['evidence'] as Map? ?? const {},
        );

        return DefaultTabController(
          length: personas.length,
          child: Scaffold(
            appBar: AppBar(
              title: const Text('استوديو الرسائل'),
              bottom: TabBar(
                isScrollable: true,
                tabs: [
                  for (final persona in personas)
                    Tab(
                      text:
                          (persona['profile'] as Map?)?['name']?.toString() ??
                          'شخصية',
                    ),
                ],
              ),
            ),
            body: Column(
              children: [
                // الفرضية تُعلن قبل أي رقم: الدرجة ترتيب لا تنبؤ.
                _EvidenceBanner(evidence: evidence),
                _ScopePicker(
                  channels: channels,
                  objectives: objectives,
                  channel: _channel,
                  objective: _objective,
                  enabled: !_busy,
                  onChanged: (channel, objective) => setState(() {
                    _channel = channel;
                    _objective = objective;
                  }),
                ),
                Expanded(
                  child: TabBarView(
                    children: [
                      for (final persona in personas)
                        _PersonaTab(
                          persona: persona,
                          channel: _channel,
                          objective: _objective,
                          maxLength: (limits[_channel] as num?)?.toInt() ?? 300,
                          results: results,
                          busy: _busy,
                          onSuggest: () => _run(
                            () => widget.repository.suggestMessages(
                              widget.projectSlug,
                              channel: _channel,
                              objective: _objective,
                              personaKey: persona['persona_key'] as String,
                            ),
                            success: 'كُتبت مسودة لهذه الشخصية.',
                          ),
                          onSave: (content, parentId) => _run(
                            () => widget.repository.saveMessageVariant(
                              widget.projectSlug,
                              personaKey: persona['persona_key'] as String,
                              channel: _channel,
                              objective: _objective,
                              content: content,
                              parentId: parentId,
                            ),
                            success: 'حُفظ الإصدار.',
                          ),
                          onTest: (variantId) => _run(
                            () => widget.repository.testMessages(
                              widget.projectSlug,
                              variantId: variantId,
                            ),
                            success: 'جاهز — رأي هذه الشخصية بالأسفل.',
                          ),
                          onStatus: (variantId, status) => _run(
                            () => widget.repository.setMessageStatus(
                              widget.projectSlug,
                              variantId,
                              status,
                            ),
                            success: status == 'approved'
                                ? 'اعتُمد الإصدار.'
                                : 'أُرشف الإصدار.',
                          ),
                        ),
                    ],
                  ),
                ),
              ],
            ),
          ),
        );
      },
    );
  }

  /// النتائج مفهرسة بالإصدار لا بالشخصية: الدرجة تخص نصًّا بعينه.
  Map<int, Map<String, dynamic>> _resultsByVariant(Map<String, dynamic> data) {
    final map = <int, Map<String, dynamic>>{};

    for (final batch in (data['batches'] as List? ?? const [])) {
      for (final result in ((batch as Map)['results'] as List? ?? const [])) {
        final row = Map<String, dynamic>.from(result as Map);
        final variantId = (row['message_variant_id'] as num?)?.toInt();
        if (variantId != null && !map.containsKey(variantId)) {
          map[variantId] = row;
        }
      }
    }

    return map;
  }
}

class _EvidenceBanner extends StatelessWidget {
  const _EvidenceBanner({required this.evidence});

  final Map<String, dynamic> evidence;

  @override
  Widget build(BuildContext context) {
    // الوسم على inferred وحده — المقيس يعرض أساسه لا وسمه.
    if (evidence['level'] != 'inferred') {
      return const SizedBox.shrink();
    }

    final theme = Theme.of(context);

    return Container(
      width: double.infinity,
      margin: const EdgeInsets.fromLTRB(12, 12, 12, 0),
      padding: const EdgeInsets.all(10),
      decoration: BoxDecoration(
        color: theme.colorScheme.secondaryContainer.withValues(alpha: 0.4),
        borderRadius: BorderRadius.circular(8),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Chip(
            label: Text(evidence['label']?.toString() ?? 'فرضية'),
            visualDensity: VisualDensity.compact,
          ),
          const SizedBox(width: 8),
          Expanded(
            child: Text(
              evidence['note']?.toString() ?? '',
              style: theme.textTheme.bodySmall,
            ),
          ),
        ],
      ),
    );
  }
}

class _ScopePicker extends StatelessWidget {
  const _ScopePicker({
    required this.channels,
    required this.objectives,
    required this.channel,
    required this.objective,
    required this.enabled,
    required this.onChanged,
  });

  final Map<String, dynamic> channels;
  final Map<String, dynamic> objectives;
  final String channel;
  final String objective;
  final bool enabled;
  final void Function(String channel, String objective) onChanged;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.fromLTRB(12, 12, 12, 4),
    child: Row(
      children: [
        Expanded(
          child: DropdownButtonFormField<String>(
            initialValue: channel,
            decoration: const InputDecoration(labelText: 'القناة'),
            items: [
              for (final entry in channels.entries)
                DropdownMenuItem(
                  value: entry.key,
                  child: Text(entry.value.toString()),
                ),
            ],
            onChanged: enabled
                ? (value) => onChanged(value ?? channel, objective)
                : null,
          ),
        ),
        const SizedBox(width: 8),
        Expanded(
          child: DropdownButtonFormField<String>(
            initialValue: objective,
            decoration: const InputDecoration(labelText: 'الهدف'),
            items: [
              for (final entry in objectives.entries)
                DropdownMenuItem(
                  value: entry.key,
                  child: Text(entry.value.toString()),
                ),
            ],
            onChanged: enabled
                ? (value) => onChanged(channel, value ?? objective)
                : null,
          ),
        ),
      ],
    ),
  );
}

class _PersonaTab extends StatefulWidget {
  const _PersonaTab({
    required this.persona,
    required this.channel,
    required this.objective,
    required this.maxLength,
    required this.results,
    required this.busy,
    required this.onSuggest,
    required this.onSave,
    required this.onTest,
    required this.onStatus,
  });

  final Map<String, dynamic> persona;
  final String channel;
  final String objective;
  final int maxLength;
  final Map<int, Map<String, dynamic>> results;
  final bool busy;
  final VoidCallback onSuggest;
  final void Function(String content, int? parentId) onSave;
  final void Function(int variantId) onTest;
  final void Function(int variantId, String status) onStatus;

  @override
  State<_PersonaTab> createState() => _PersonaTabState();
}

class _PersonaTabState extends State<_PersonaTab> {
  final TextEditingController _controller = TextEditingController();

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  Map<String, dynamic>? get _current {
    for (final variant in (widget.persona['variants'] as List? ?? const [])) {
      final row = Map<String, dynamic>.from(variant as Map);
      if (row['channel'] == widget.channel &&
          row['objective'] == widget.objective &&
          row['status'] != 'archived') {
        return row;
      }
    }

    return null;
  }

  @override
  Widget build(BuildContext context) {
    final profile = Map<String, dynamic>.from(
      widget.persona['profile'] as Map? ?? const {},
    );
    final current = _current;
    final variantId = (current?['id'] as num?)?.toInt();
    final result = variantId == null ? null : widget.results[variantId];

    return ListView(
      padding: const EdgeInsets.all(12),
      children: [
        BrandCard(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                '${profile['age_range'] ?? 'غير محدد'} · ${profile['gender'] ?? 'الجنسان'}',
                style: Theme.of(context).textTheme.labelSmall,
              ),
              const SizedBox(height: 4),
              Text(
                profile['name']?.toString() ?? 'شخصية',
                style: Theme.of(context).textTheme.titleMedium,
              ),
              if (profile['role'] != null) Text(profile['role'].toString()),
              const SizedBox(height: 8),
              _Fact(label: 'المدن', value: _join(profile['locations'])),
              _Fact(label: 'الاهتمامات', value: _join(profile['interests'])),
              _Fact(label: 'المنصات', value: _join(profile['platforms'])),
              _Fact(
                label: 'مستوى الإنفاق',
                value: profile['spending_level']?.toString(),
              ),
              _Fact(
                label: 'يشتري لأنه',
                value: profile['motivation']?.toString(),
              ),
              _Fact(
                label: 'يتردد لأنه',
                value: profile['objection']?.toString(),
              ),
              _Fact(label: 'النبرة', value: profile['tone']?.toString()),
              _Fact(label: 'تجنّب', value: profile['avoid']?.toString()),
            ],
          ),
        ),
        const SizedBox(height: 12),
        BrandCard(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Expanded(
                    child: Text(
                      'رسالتها — ${current?['status_label'] ?? 'بلا رسالة'}',
                      style: Theme.of(context).textTheme.titleSmall,
                    ),
                  ),
                  TextButton(
                    onPressed: widget.busy ? null : widget.onSuggest,
                    child: const Text('اقترح لي'),
                  ),
                ],
              ),
              if (current != null) ...[
                const SizedBox(height: 6),
                SelectableText(current['content'].toString()),
                if (current['teaching_note'] != null) ...[
                  const SizedBox(height: 6),
                  Text(
                    'لماذا تناسبها: ${current['teaching_note']}',
                    style: Theme.of(context).textTheme.bodySmall,
                  ),
                ],
                const SizedBox(height: 8),
                Wrap(
                  spacing: 6,
                  children: [
                    OutlinedButton(
                      onPressed: widget.busy
                          ? null
                          : () {
                              Clipboard.setData(
                                ClipboardData(
                                  text: current['content'].toString(),
                                ),
                              );
                              ScaffoldMessenger.of(context).showSnackBar(
                                const SnackBar(content: Text('نُسخت الرسالة.')),
                              );
                            },
                      child: const Text('انسخ'),
                    ),
                    FilledButton(
                      onPressed: widget.busy
                          ? null
                          : () => widget.onTest(variantId!),
                      child: const Text('اختبرها وحدها'),
                    ),
                    if (current['status'] != 'approved')
                      TextButton(
                        onPressed: widget.busy
                            ? null
                            : () => widget.onStatus(variantId!, 'approved'),
                        child: const Text('اعتمد'),
                      ),
                    TextButton(
                      onPressed: widget.busy
                          ? null
                          : () => widget.onStatus(variantId!, 'archived'),
                      child: const Text('أرشف'),
                    ),
                  ],
                ),
              ] else
                const Padding(
                  padding: EdgeInsets.only(top: 6),
                  child: Text('لا رسالة على هذه القناة بعد.'),
                ),
            ],
          ),
        ),
        const SizedBox(height: 12),
        BrandCard(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                current == null ? 'اكتب بنفسك' : 'اكتب إصدارًا جديدًا',
                style: Theme.of(context).textTheme.titleSmall,
              ),
              const SizedBox(height: 6),
              TextField(
                controller: _controller,
                maxLines: 4,
                maxLength: widget.maxLength,
                decoration: const InputDecoration(
                  hintText: 'نصّ الرسالة كما سيُنشر…',
                ),
              ),
              Align(
                alignment: AlignmentDirectional.centerStart,
                child: OutlinedButton(
                  onPressed: widget.busy
                      ? null
                      : () {
                          final content = _controller.text.trim();
                          if (content.length < 20) return;
                          widget.onSave(content, variantId);
                          _controller.clear();
                        },
                  child: const Text('احفظ الإصدار'),
                ),
              ),
            ],
          ),
        ),
        if (result != null) ...[
          const SizedBox(height: 12),
          BrandCard(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Text(
                      'رأيها: ${result['score']}/100',
                      style: Theme.of(context).textTheme.titleSmall,
                    ),
                    const SizedBox(width: 8),
                    if (result['evidence_level'] == 'inferred')
                      Chip(
                        label: Text(
                          result['evidence_label']?.toString() ?? 'فرضية',
                        ),
                        visualDensity: VisualDensity.compact,
                      ),
                  ],
                ),
                const SizedBox(height: 6),
                Text(result['reaction'].toString()),
                if (result['strength'] != null)
                  _Fact(label: 'ما نجح', value: result['strength'].toString()),
                if (result['objection'] != null)
                  _Fact(label: 'ما بقي', value: result['objection'].toString()),
                if (result['revised_content'] != null) ...[
                  const SizedBox(height: 6),
                  Text(
                    'تعديل مقترح لها وحدها:',
                    style: Theme.of(context).textTheme.labelMedium,
                  ),
                  SelectableText(result['revised_content'].toString()),
                ],
              ],
            ),
          ),
        ],
      ],
    );
  }

  String? _join(dynamic value) {
    if (value is List && value.isNotEmpty) {
      return value.join('، ');
    }

    return null;
  }
}

class _Fact extends StatelessWidget {
  const _Fact({required this.label, required this.value});

  final String label;
  final String? value;

  @override
  Widget build(BuildContext context) {
    if (value == null || value!.isEmpty) {
      return const SizedBox.shrink();
    }

    return Padding(
      padding: const EdgeInsets.only(top: 4),
      child: RichText(
        text: TextSpan(
          style: Theme.of(context).textTheme.bodySmall,
          children: [
            TextSpan(
              text: '$label: ',
              style: const TextStyle(fontWeight: FontWeight.w700),
            ),
            TextSpan(text: value),
          ],
        ),
      ),
    );
  }
}
