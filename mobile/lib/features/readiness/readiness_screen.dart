import 'package:flutter/material.dart';

import '../../core/api/api_exception.dart';
import '../../core/api/platform_repository.dart';
import '../../core/widgets/adaptive_layout.dart';
import '../../core/widgets/common.dart';

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
  late Future<Map<String, dynamic>> _data;
  bool _busy = false;

  @override
  void initState() {
    super.initState();
    _reload();
  }

  void _reload() {
    _data = widget.repository.readiness(widget.projectSlug);
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
                _maturity(context, _map(data['maturity'])),
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
                _axes(context, _map(data['maturity'])),
                const SizedBox(height: 24),
                _fixes(context, data['fixes'] as List<dynamic>?),
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

  Widget _fixes(BuildContext context, List<dynamic>? fixes) {
    if (fixes == null || fixes.isEmpty) return const SizedBox.shrink();

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
        for (final raw in fixes.take(10)) _fixTile(context, _map(raw)!),
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
