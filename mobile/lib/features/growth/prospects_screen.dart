import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../core/api/api_exception.dart';
import '../../core/api/platform_repository.dart';
import '../../core/widgets/common.dart';

/// العملاء المتوقعون على الجوال: بطاقة لكل شخص باسمه ورسالته.
///
/// لا درجة ولا رد متوقَّع هنا: هؤلاء أشخاص حقيقيون، ومحاكاة رأي إنسان
/// مُسمّى تختلق موقفًا لم يتخذه.
class ProspectsScreen extends StatefulWidget {
  const ProspectsScreen({
    super.key,
    required this.repository,
    required this.projectSlug,
    required this.projectName,
  });

  final PlatformRepository repository;
  final String projectSlug;
  final String projectName;

  @override
  State<ProspectsScreen> createState() => _ProspectsScreenState();
}

class _ProspectsScreenState extends State<ProspectsScreen> {
  late Future<Map<String, dynamic>> _data;
  String _channel = 'whatsapp';
  String _objective = 'trust';
  bool _busy = false;

  @override
  void initState() {
    super.initState();
    _reload();
  }

  void _reload() {
    _data = widget.repository.prospects(widget.projectSlug);
  }

  Future<void> _run(
    Future<dynamic> Function() action, {
    required String success,
  }) async {
    setState(() => _busy = true);
    try {
      final result = await action();
      if (!mounted) return;
      setState(_reload);

      // ما لم يكتمل وما تجاوز السقف يُقالان للمستخدم، لا يُبتلعان.
      final buffer = StringBuffer(success);
      if (result is Map) {
        final incomplete = result['incomplete'];
        if (incomplete is List && incomplete.isNotEmpty) {
          buffer.write(' لم تكتمل: ${incomplete.join('، ')}.');
        }
        final skipped = (result['skipped'] as num?)?.toInt() ?? 0;
        if (skipped > 0) {
          buffer.write(' تُركت $skipped خارج الدفعة — أعد التوليد لبقيتهم.');
        }
      }

      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(buffer.toString())));
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
      future: _data,
      builder: (context, snapshot) {
        if (snapshot.hasError) {
          return Scaffold(
            appBar: AppBar(title: const Text('العملاء المتوقعون')),
            body: Center(child: Text('${snapshot.error}')),
          );
        }

        if (!snapshot.hasData) {
          return Scaffold(
            appBar: AppBar(title: const Text('العملاء المتوقعون')),
            body: const Center(child: CircularProgressIndicator()),
          );
        }

        final data = snapshot.data!;
        final prospects = (data['prospects'] as List? ?? const [])
            .map((item) => Map<String, dynamic>.from(item as Map))
            .toList();
        final channels = Map<String, dynamic>.from(
          data['channels'] as Map? ?? const {},
        );
        final objectives = Map<String, dynamic>.from(
          data['objectives'] as Map? ?? const {},
        );
        final limit = (data['batch_limit'] as num?)?.toInt() ?? 10;

        return Scaffold(
          appBar: AppBar(title: const Text('العملاء المتوقعون')),
          floatingActionButton: FloatingActionButton.extended(
            onPressed: _busy ? null : () => _addProspect(data),
            icon: const Icon(Icons.person_add_alt),
            label: const Text('أضف'),
          ),
          body: ListView(
            padding: const EdgeInsets.all(12),
            children: [
              BrandCard(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Expanded(
                          child: DropdownButtonFormField<String>(
                            initialValue: _channel,
                            decoration: const InputDecoration(
                              labelText: 'القناة',
                            ),
                            items: [
                              for (final entry in channels.entries)
                                DropdownMenuItem(
                                  value: entry.key,
                                  child: Text(entry.value.toString()),
                                ),
                            ],
                            onChanged: _busy
                                ? null
                                : (value) => setState(
                                    () => _channel = value ?? _channel,
                                  ),
                          ),
                        ),
                        const SizedBox(width: 8),
                        Expanded(
                          child: DropdownButtonFormField<String>(
                            initialValue: _objective,
                            decoration: const InputDecoration(
                              labelText: 'الهدف',
                            ),
                            items: [
                              for (final entry in objectives.entries)
                                DropdownMenuItem(
                                  value: entry.key,
                                  child: Text(entry.value.toString()),
                                ),
                            ],
                            onChanged: _busy
                                ? null
                                : (value) => setState(
                                    () => _objective = value ?? _objective,
                                  ),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 8),
                    FilledButton(
                      onPressed: _busy || prospects.isEmpty
                          ? null
                          : () => _run(
                              () => widget.repository.generateProspectMessages(
                                widget.projectSlug,
                                channel: _channel,
                                objective: _objective,
                              ),
                              success: 'الرسائل جاهزة.',
                            ),
                      child: const Text('ولّد رسالة لكل عميل'),
                    ),
                    Text(
                      'حتى $limit عملاء في الدفعة الواحدة — سقف يحمي ميزانية استعلاماتك.',
                      style: Theme.of(context).textTheme.bodySmall,
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 12),
              if (prospects.isEmpty)
                const Padding(
                  padding: EdgeInsets.all(16),
                  child: Text('لا عملاء متوقعون بعد — أضف أول اسم.'),
                ),
              for (final prospect in prospects)
                _ProspectCard(
                  prospect: prospect,
                  busy: _busy,
                  objective: _objective,
                  onGenerate: () => _run(
                    () => widget.repository.generateProspectMessages(
                      widget.projectSlug,
                      channel: prospect['preferred_channel'] as String,
                      objective: _objective,
                      prospectId: (prospect['id'] as num).toInt(),
                    ),
                    success: 'جاهزة.',
                  ),
                  onSent: (messageId) => _run(
                    () => widget.repository.markProspectMessageSent(
                      widget.projectSlug,
                      messageId,
                    ),
                    success: 'سُجّلت كمُرسَلة.',
                  ),
                  onStatus: (status) => _run(
                    () => widget.repository.updateProspectStatus(
                      widget.projectSlug,
                      (prospect['id'] as num).toInt(),
                      status,
                    ),
                    success: status == 'won' ? 'مبروك.' : 'أُرشف.',
                  ),
                ),
            ],
          ),
        );
      },
    );
  }

  Future<void> _addProspect(Map<String, dynamic> data) async {
    final name = TextEditingController();
    final city = TextEditingController();
    final notes = TextEditingController();
    final temperatures = Map<String, dynamic>.from(
      data['temperatures'] as Map? ?? const {},
    );
    var temperature = 'warm';

    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => StatefulBuilder(
        builder: (context, setLocal) => AlertDialog(
          title: const Text('عميل متوقع'),
          content: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                TextField(
                  controller: name,
                  decoration: const InputDecoration(labelText: 'الاسم'),
                ),
                TextField(
                  controller: city,
                  decoration: const InputDecoration(labelText: 'المدينة'),
                ),
                TextField(
                  controller: notes,
                  maxLines: 3,
                  decoration: const InputDecoration(
                    labelText: 'ما تعرفه عنه',
                    helperText: 'ما لا تكتبه هنا لن يُخترع لك.',
                  ),
                ),
                DropdownButtonFormField<String>(
                  initialValue: temperature,
                  decoration: const InputDecoration(labelText: 'حرارة النية'),
                  items: [
                    for (final entry in temperatures.entries)
                      DropdownMenuItem(
                        value: entry.key,
                        child: Text(entry.value.toString()),
                      ),
                  ],
                  onChanged: (value) =>
                      setLocal(() => temperature = value ?? temperature),
                ),
              ],
            ),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(context, false),
              child: const Text('إلغاء'),
            ),
            FilledButton(
              onPressed: () => Navigator.pop(context, true),
              child: const Text('أضِف'),
            ),
          ],
        ),
      ),
    );

    if (confirmed != true || name.text.trim().isEmpty) return;

    await _run(
      () => widget.repository.addProspect(
        widget.projectSlug,
        name: name.text.trim(),
        temperature: temperature,
        preferredChannel: _channel,
        city: city.text.trim().isEmpty ? null : city.text.trim(),
        notes: notes.text.trim().isEmpty ? null : notes.text.trim(),
      ),
      success: 'أُضيف.',
    );
  }
}

class _ProspectCard extends StatelessWidget {
  const _ProspectCard({
    required this.prospect,
    required this.busy,
    required this.objective,
    required this.onGenerate,
    required this.onSent,
    required this.onStatus,
  });

  final Map<String, dynamic> prospect;
  final bool busy;
  final String objective;
  final VoidCallback onGenerate;
  final void Function(int messageId) onSent;
  final void Function(String status) onStatus;

  @override
  Widget build(BuildContext context) {
    final messages = (prospect['messages'] as List? ?? const [])
        .map((item) => Map<String, dynamic>.from(item as Map))
        .where((row) => row['status'] != 'archived')
        .toList();
    final message = messages.isEmpty ? null : messages.first;
    final details = [prospect['role'], prospect['organization'], prospect['city']]
        .where((value) => value != null && value.toString().isNotEmpty)
        .join(' · ');

    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: BrandCard(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        prospect['name'].toString(),
                        style: Theme.of(context).textTheme.titleMedium,
                      ),
                      if (details.isNotEmpty) Text(details),
                      Text(
                        prospect['temperature_label']?.toString() ?? '',
                        style: Theme.of(context).textTheme.labelSmall,
                      ),
                    ],
                  ),
                ),
                TextButton(
                  onPressed: busy ? null : onGenerate,
                  child: Text(message == null ? 'ولّد رسالته' : 'نسخة جديدة'),
                ),
              ],
            ),
            if (message != null) ...[
              const SizedBox(height: 8),
              SelectableText(message['content'].toString()),
              if (message['why'] != null)
                Padding(
                  padding: const EdgeInsets.only(top: 4),
                  child: Text(
                    'لماذا هكذا: ${message['why']}',
                    style: Theme.of(context).textTheme.bodySmall,
                  ),
                ),
              const SizedBox(height: 8),
              Wrap(
                spacing: 6,
                children: [
                  OutlinedButton(
                    onPressed: busy
                        ? null
                        : () {
                            Clipboard.setData(
                              ClipboardData(
                                text: message['content'].toString(),
                              ),
                            );
                            ScaffoldMessenger.of(context).showSnackBar(
                              const SnackBar(content: Text('نُسخت الرسالة.')),
                            );
                          },
                    child: const Text('انسخ'),
                  ),
                  if (message['status'] != 'sent')
                    TextButton(
                      onPressed: busy
                          ? null
                          : () => onSent((message['id'] as num).toInt()),
                      child: const Text('سجّلها كمُرسَلة'),
                    ),
                  TextButton(
                    onPressed: busy ? null : () => onStatus('won'),
                    child: const Text('صار عميلًا'),
                  ),
                  TextButton(
                    onPressed: busy ? null : () => onStatus('archived'),
                    child: const Text('أرشفه'),
                  ),
                ],
              ),
            ] else
              const Padding(
                padding: EdgeInsets.only(top: 6),
                child: Text('لا رسالة له بعد.'),
              ),
          ],
        ),
      ),
    );
  }
}
