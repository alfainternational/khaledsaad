import 'package:flutter/material.dart';

import '../../core/api/api_exception.dart';
import '../../core/api/platform_repository.dart';
import '../../core/widgets/adaptive_layout.dart';
import '../../core/widgets/common.dart';

/// حضورك في إجابات الذكاء: نظير شاشة الويب حرفيًّا.
///
/// الخادم يحسب والشاشة تعرض. كل نسبة هنا تأتي بأسماء §١٢ نفسها ومعها أساسها،
/// فلا يقول السطحان رقمين مختلفين لنفس النشاط.
class PresenceScreen extends StatefulWidget {
  const PresenceScreen({
    super.key,
    required this.repository,
    required this.projectSlug,
    required this.projectName,
  });

  final PlatformRepository repository;
  final String projectSlug;
  final String projectName;

  @override
  State<PresenceScreen> createState() => _PresenceScreenState();
}

class _PresenceScreenState extends State<PresenceScreen> {
  late Future<Map<String, dynamic>> _data;
  bool _busy = false;

  @override
  void initState() {
    super.initState();
    _reload();
  }

  void _reload() {
    _data = widget.repository.presence(widget.projectSlug);
  }

  Future<void> _probe() async {
    setState(() => _busy = true);

    try {
      await widget.repository.probePresence(widget.projectSlug);
      if (!mounted) return;

      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('بدأ الاستطلاع. سيظهر التقرير هنا عند اكتماله.'),
        ),
      );
      setState(_reload);
    } on ApiException catch (error) {
      if (!mounted) return;
      // رسالة السقف تُعرض بنصّها: هي إرشاد بميزانية لا عطل يُبتلع.
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(error.message)));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return AdaptiveScaffold(
      family: AdaptivePageFamily.operational,
      appBar: AppBar(title: const Text('حضورك في إجابات الذكاء')),
      body: FutureBuilder<Map<String, dynamic>>(
        future: _data,
        builder: (context, snapshot) => AsyncView<Map<String, dynamic>>(
          snapshot: snapshot,
          onRetry: () => setState(_reload),
          builder: (data) => SingleChildScrollView(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Eyebrow(widget.projectName),
                const SizedBox(height: 12),
                _budget(context, _map(data['budget'])),
                const SizedBox(height: 12),
                FilledButton(
                  onPressed: _busy ? null : _probe,
                  child: _busy
                      ? const SizedBox(
                          height: 18,
                          width: 18,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        )
                      : const Text('ابدأ استطلاعًا جديدًا'),
                ),
                const SizedBox(height: 24),
                _metrics(context, _map(data['metrics'])),
                const SizedBox(height: 24),
                _sources(context, _map(data['source_map'])),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Widget _budget(BuildContext context, Map<String, dynamic>? budget) {
    if (budget == null) return const SizedBox.shrink();

    final remaining = (budget['remaining'] as num?)?.toInt() ?? 0;
    final limit = (budget['monthly_limit'] as num?)?.toInt() ?? 0;

    return Text(
      'متبقٍّ من سقف هذا الشهر: $remaining من $limit استعلامًا.',
      style: Theme.of(context).textTheme.bodySmall,
    );
  }

  Widget _metrics(BuildContext context, Map<String, dynamic>? metrics) {
    if (metrics == null) {
      // لا أصفار لمن لم يستطلع: الصفر يُقرأ حكمًا على النشاط (§٤.٣).
      return const EmptyState(
        title: 'لم يُشغَّل استطلاع بعد',
        message: 'ابدأ استطلاعًا لتعرف هل تذكرك النماذج حين يسأل عنك مشترٍ.',
      );
    }

    final basis = _map(metrics['basis']) ?? const {};
    final attempts = (basis['successful_attempts'] as num?)?.toInt() ?? 0;

    return BrandCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          // المقياسان بتسميتين ظاهرتين: مقاماهما مختلفان تمامًا، وخلطهما
          // يعطي صاحب النشاط قراءة معكوسة عن موقعه (§١٢).
          _ratio(
            context,
            'معدّل الذكر — كم مرة ذُكرت من محاولاتنا',
            metrics['mention_rate'],
            basisLabel: 'من $attempts محاولة',
          ),
          const Divider(),
          _ratio(
            context,
            'حصة الصوت — نصيبك من ذكر كل العلامات',
            metrics['share_of_voice'],
            emptyLabel: 'لم تُذكر أي علامة',
          ),
          const Divider(),
          _ratio(
            context,
            'معدّل الاستشهاد — كم مرة ذُكرت ومعك رابط موقعك',
            metrics['citation_rate'],
            emptyLabel: 'لم تُذكر بعد',
          ),
          if (metrics['publishable'] != true) ...[
            const SizedBox(height: 12),
            Text(
              'الدورة ناقصة: نجحت $attempts محاولة من '
              '${(basis['planned_attempts'] as num?)?.toInt() ?? 0}. '
              'الأرقام أعلاه على ما نجح فعلًا.',
              style: Theme.of(context).textTheme.bodySmall,
            ),
          ],
          const SizedBox(height: 8),
          Text(
            'المصدر: ${basis['provider'] ?? '—'} · ${basis['model'] ?? '—'}',
            style: Theme.of(context).textTheme.bodySmall,
          ),
        ],
      ),
    );
  }

  Widget _ratio(
    BuildContext context,
    String label,
    dynamic value, {
    String? basisLabel,
    String emptyLabel = 'لم يُقَس',
  }) {
    final ratio = (value as num?)?.toDouble();

    return ListTile(
      contentPadding: EdgeInsets.zero,
      title: Text(label),
      subtitle: ratio == null
          ? Text(emptyLabel)
          : Text(
              basisLabel == null
                  ? '${(ratio * 100).round()}٪'
                  : '${(ratio * 100).round()}٪ · $basisLabel',
            ),
    );
  }

  Widget _sources(BuildContext context, Map<String, dynamic>? map) {
    if (map == null || map['available'] != true) {
      return const SizedBox.shrink();
    }

    final sources = (map['sources'] as List<dynamic>?) ?? const [];

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          'المصادر التي تستشهد بها النماذج',
          style: Theme.of(context).textTheme.titleMedium,
        ),
        const SizedBox(height: 4),
        Text('من ${map['attempts']} محاولة مقروءة.'),
        const SizedBox(height: 8),
        for (final raw in sources.take(10)) _sourceTile(context, _map(raw)!),
      ],
    );
  }

  Widget _sourceTile(BuildContext context, Map<String, dynamic> source) {
    final share = ((source['share'] as num?)?.toDouble() ?? 0) * 100;

    return ListTile(
      contentPadding: EdgeInsets.zero,
      title: Text(source['host']?.toString() ?? ''),
      subtitle: Text('${source['citations']} استشهاد · ${share.round()}٪'),
      trailing: source['is_own'] == true
          ? const Chip(
              label: Text('موقعك'),
              visualDensity: VisualDensity.compact,
            )
          : null,
    );
  }

  Map<String, dynamic>? _map(dynamic value) =>
      value is Map ? Map<String, dynamic>.from(value) : null;
}
