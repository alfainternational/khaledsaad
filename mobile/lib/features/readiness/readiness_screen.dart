import 'package:flutter/material.dart';

import '../../core/api/api_exception.dart';
import '../../core/api/platform_repository.dart';
import '../../core/widgets/adaptive_layout.dart';
import '../../core/widgets/common.dart';
import 'models.dart';

/// الجاهزية للذكاء الاصطناعي: نظير شاشة الويب حرفيًّا.
///
/// الخادم يحسب والشاشة تعرض. كل رقم هنا يأتي بأسماء §١٢ نفسها التي يعرضها
/// الموقع، فلا يقول السطحان رقمين مختلفين لنفس النشاط.
class ReadinessScreen extends StatefulWidget {
  const ReadinessScreen({
    super.key,
    required this.repository,
    required this.projectSlug,
    required this.projectName,
  });

  final PlatformRepository repository;
  final String projectSlug;
  final String projectName;

  @override
  State<ReadinessScreen> createState() => _ReadinessScreenState();
}

class _ReadinessScreenState extends State<ReadinessScreen> {
  late Future<ReadinessOverview> _data;
  bool _busy = false;

  @override
  void initState() {
    super.initState();
    _reload();
  }

  void _reload() {
    _data = widget.repository
        .readiness(widget.projectSlug)
        .then(ReadinessOverview.fromJson);
  }

  Future<void> _runAudit() async {
    setState(() => _busy = true);

    try {
      await widget.repository.runReadinessAudit(widget.projectSlug);
      if (!mounted) return;
      setState(_reload);
    } on ApiException catch (error) {
      if (!mounted) return;
      // الرسالة تُعرض بنصّها: «أضف رابط موقعك» إرشاد لا عطل يُبتلع.
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(error.message)));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    // AdaptiveScaffold يلفّ المحتوى بـAdaptivePage بنفسه؛ لفّه ثانيةً يضاعف
    // الحشو ويقيّد العرض مرتين.
    return AdaptiveScaffold(
      family: AdaptivePageFamily.operational,
      appBar: AppBar(title: const Text('الجاهزية للذكاء الاصطناعي')),
      body: FutureBuilder<ReadinessOverview>(
        future: _data,
        builder: (context, snapshot) => AsyncView<ReadinessOverview>(
          snapshot: snapshot,
          onRetry: () => setState(_reload),
          builder: (data) => SingleChildScrollView(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Eyebrow(widget.projectName),
                const SizedBox(height: 12),
                _maturity(context, data.maturity),
                const SizedBox(height: 16),
                FilledButton(
                  onPressed: _busy ? null : _runAudit,
                  child: _busy
                      ? const SizedBox(
                          height: 18,
                          width: 18,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        )
                      : const Text('افحص موقعي'),
                ),
                const SizedBox(height: 24),
                _conflicts(context, data.conflicts),
                _trend(context, data),
                _benchmark(context, data.benchmark),
                _axes(context, data.maturity),
                const SizedBox(height: 24),
                _fixes(context, data.fixes),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Widget _maturity(BuildContext context, Map<String, dynamic>? maturity) {
    final active = (maturity?['axes_active'] as num?)?.toInt() ?? 0;

    if (maturity == null || active == 0) {
      // غياب القياس يُقال غيابًا: الصفر يُقرأ حكمًا على النشاط (§٤.٣).
      return const EmptyState(
        title: 'لم يُقَس أي محور بعد',
        message: 'شغّل الفحص لتظهر درجتك ومحاورك.',
      );
    }

    final total = (maturity['axes_total'] as num?)?.toInt() ?? 0;

    return BrandCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          BigScore(
            score: (maturity['maturity_score'] as num?)?.toInt() ?? 0,
            band: 'درجة النضج التسويقي',
          ),
          const SizedBox(height: 8),
          // الرقم مع أساسه دائمًا (§١٣).
          Text(
            'محسوبة من $active محاور مقيسة من $total',
            textAlign: TextAlign.center,
            style: Theme.of(context).textTheme.bodySmall,
          ),
        ],
      ),
    );
  }

  /// «تحتاج مراجعتك»: التعارض يُعرض بقوليه ولا يُحسم صامتًا (§٩).
  ///
  /// أن يقول نشاطك شيئًا وتقول بياناتك غيره معلومةٌ حقيقية عنه. الويب يعرضها
  /// والتطبيق كان يبتلعها — ومسطّحان يريان الدماغ نفسه بوجهين مختلفين.
  Widget _conflicts(BuildContext context, List<BrainConflict> conflicts) {
    if (conflicts.isEmpty) return const SizedBox.shrink();

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text('تحتاج مراجعتك', style: Theme.of(context).textTheme.titleMedium),
        const SizedBox(height: 4),
        const Text(
          'مصدران قالا شيئين مختلفين عن نفس المعلومة. لم نحسم أيّهما أصدق.',
        ),
        const SizedBox(height: 8),
        for (final conflict in conflicts) _conflictTile(context, conflict),
        const SizedBox(height: 24),
      ],
    );
  }

  Widget _conflictTile(BuildContext context, BrainConflict conflict) {
    return BrandCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(conflict.key, style: Theme.of(context).textTheme.titleSmall),
          const SizedBox(height: 6),
          for (final side in conflict.sides)
            Padding(
              padding: const EdgeInsets.symmetric(vertical: 2),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Text(side.source),
                  Flexible(
                    child: Text(
                      side.value,
                      textAlign: TextAlign.end,
                      style: const TextStyle(fontWeight: FontWeight.bold),
                    ),
                  ),
                ],
              ),
            ),
          if (conflict.revisions > 1) ...[
            const SizedBox(height: 6),
            Text(
              'تغيّرت هذه المعلومة ${conflict.revisions} مرات قبل التعارض.',
              style: Theme.of(context).textTheme.bodySmall,
            ),
          ],
        ],
      ),
    );
  }

  /// الاتجاه لا يُرسم قبل أربع نقاط (§١٣).
  ///
  /// قبلها تُعرض النقاط بسببٍ صريح: خطٌّ من نقطتين يُقرأ اتجاهًا ويُتَّخذ عليه
  /// قرار. `plottable` تأتي من الخادم ولا تُعاد هنا — عتبةٌ في مكانين تتباعد.
  Widget _trend(BuildContext context, ReadinessOverview data) {
    if (data.history.isEmpty) return const SizedBox.shrink();

    if (!data.plottable) {
      if (data.history.length < 2) return const SizedBox.shrink();

      return Padding(
        padding: const EdgeInsets.only(bottom: 16),
        child: Text(
          'عندك ${data.history.length} قياسات. الاتجاه يُرسم عند أربعة قياسات '
          'فأكثر — أقل من ذلك لا يفرّق بين تحسّن حقيقي وتذبذب عادي.',
          style: Theme.of(context).textTheme.bodySmall,
        ),
      );
    }

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text('اتجاه الدرجة', style: Theme.of(context).textTheme.titleMedium),
        const SizedBox(height: 8),
        SingleChildScrollView(
          scrollDirection: Axis.horizontal,
          child: Row(
            children: [
              for (final point in data.history)
                Padding(
                  padding: const EdgeInsetsDirectional.only(end: 16),
                  child: Column(
                    children: [
                      Text(
                        '${point.maturityScore}',
                        style: Theme.of(context).textTheme.titleMedium,
                      ),
                      Text(
                        _day(point.occurredAt),
                        style: Theme.of(context).textTheme.bodySmall,
                      ),
                    ],
                  ),
                ),
            ],
          ),
        ),
        const SizedBox(height: 16),
      ],
    );
  }

  /// موقعه من قطاعه، أو سبب غياب المقارنة صراحةً — لا متوسط تقريبي (§٤.٣).
  Widget _benchmark(BuildContext context, IndustryBenchmarkView benchmark) {
    final style = Theme.of(context).textTheme.bodySmall;

    if (!benchmark.available) {
      final reason = benchmark.reason;

      if (reason == null || reason.isEmpty) return const SizedBox.shrink();

      return Padding(
        padding: const EdgeInsets.only(bottom: 16),
        child: Text(reason, style: style),
      );
    }

    final buffer = StringBuffer(
      'متوسط «${benchmark.industry}» ${benchmark.industryAverage}/100 '
      'من ${benchmark.sampleSize} نشاطًا مقيسًا.',
    );

    final delta = benchmark.delta;

    if (delta != null) {
      final side = delta >= 0 ? 'أعلى بـ$delta' : 'أدنى بـ${delta.abs()}';
      buffer.write(' أنت $side نقطة، فوق ${benchmark.percentile}٪ من أنشطة قطاعك.');
    }

    return Padding(
      padding: const EdgeInsets.only(bottom: 16),
      child: Text(buffer.toString(), style: style),
    );
  }

  String _day(DateTime? at) =>
      at == null ? '—' : '${at.day}/${at.month}';

  Widget _axes(BuildContext context, Map<String, dynamic>? maturity) {
    final axes = (maturity?['axes'] as List<dynamic>?) ?? const [];

    if (axes.isEmpty) return const SizedBox.shrink();

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text('محاور التشخيص', style: Theme.of(context).textTheme.titleMedium),
        const SizedBox(height: 8),
        for (final raw in axes) _axisTile(context, _map(raw)!),
      ],
    );
  }

  Widget _axisTile(BuildContext context, Map<String, dynamic> axis) {
    final isActive = axis['active'] == true;
    final coverage = ((axis['axis_coverage'] as num?)?.toDouble() ?? 0) * 100;

    return ListTile(
      contentPadding: EdgeInsets.zero,
      title: Text(axis['label']?.toString() ?? ''),
      subtitle: Text(
        isActive
            ? '${axis['axis_score']}/100 · تغطية ${coverage.round()}٪'
            : 'لم يُقَس',
      ),
      trailing: Chip(
        label: Text(axis['is_assumption'] == true ? 'فرضية' : 'مقيس'),
        visualDensity: VisualDensity.compact,
      ),
    );
  }

  Widget _fixes(BuildContext context, List<Map<String, dynamic>> fixes) {
    if (fixes.isEmpty) return const SizedBox.shrink();

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          'ما أصلحه هذا الأسبوع',
          style: Theme.of(context).textTheme.titleMedium,
        ),
        const SizedBox(height: 4),
        const Text('مرتّبة على الأثر ثم الجهد — ابدأ من الأعلى.'),
        const SizedBox(height: 8),
        for (final fix in fixes.take(10)) _fixTile(context, fix),
      ],
    );
  }

  Widget _fixTile(BuildContext context, Map<String, dynamic> fix) {
    final repair = fix['fix']?.toString();

    return ListTile(
      contentPadding: EdgeInsets.zero,
      title: Text(fix['title']?.toString() ?? ''),
      subtitle: repair == null || repair.isEmpty ? null : Text(repair),
      trailing: Chip(
        label: Text(fix['effort_label']?.toString() ?? ''),
        visualDensity: VisualDensity.compact,
      ),
    );
  }

  Map<String, dynamic>? _map(dynamic value) =>
      value is Map ? Map<String, dynamic>.from(value) : null;
}
