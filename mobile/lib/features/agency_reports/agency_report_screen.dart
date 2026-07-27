import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:open_filex/open_filex.dart';
import 'package:path_provider/path_provider.dart';

import '../../core/api/platform_repository.dart';
import '../../core/theme/app_theme.dart';
import '../../core/widgets/common.dart';
import 'models.dart';

class AgencyReportScreen extends StatefulWidget {
  const AgencyReportScreen({
    super.key,
    required this.repository,
    required this.uuid,
    this.initial,
  });

  final PlatformRepository repository;
  final String uuid;
  final AgencyReportDetail? initial;

  @override
  State<AgencyReportScreen> createState() => _AgencyReportScreenState();
}

class _AgencyReportScreenState extends State<AgencyReportScreen> {
  late Future<AgencyReportDetail> _future = widget.initial == null
      ? widget.repository.agencyReport(widget.uuid)
      : Future.value(widget.initial);
  bool _downloading = false;
  bool _sharing = false;
  bool _regenerating = false;
  late String _activeUuid = widget.uuid;
  AgencyShare? _share;

  void _reload() {
    setState(() {
      _share = null;
      _future = widget.repository.agencyReport(_activeUuid);
    });
  }

  Future<void> _download() async {
    setState(() => _downloading = true);

    try {
      final bytes = await widget.repository.agencyReportPdf(_activeUuid);
      final dir = await getTemporaryDirectory();
      final file = File('${dir.path}/agency-report-$_activeUuid.pdf');
      await file.writeAsBytes(bytes, flush: true);
      await OpenFilex.open(file.path);
    } catch (error) {
      _notify(error.toString());
    } finally {
      if (mounted) setState(() => _downloading = false);
    }
  }

  Future<void> _createShare(List<int> choices) async {
    final days = await showModalBottomSheet<int>(
      context: context,
      builder: (context) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Padding(
              padding: EdgeInsets.all(16),
              child: Text(
                'مدة صلاحية الرابط',
                style: TextStyle(fontSize: 17, fontWeight: FontWeight.w700),
              ),
            ),
            for (final option in choices)
              ListTile(
                title: Text('$option يومًا'),
                onTap: () => Navigator.pop(context, option),
              ),
          ],
        ),
      ),
    );

    if (days == null) return;

    setState(() => _sharing = true);

    try {
      final share = await widget.repository.shareAgencyReport(
        _activeUuid,
        days,
      );
      if (mounted) setState(() => _share = share);
      _notify('أُنشئ رابط مشاركة صالح $days يومًا.');
    } catch (error) {
      _notify(error.toString());
    } finally {
      if (mounted) setState(() => _sharing = false);
    }
  }

  Future<void> _revokeShare() async {
    setState(() => _sharing = true);

    try {
      final share = await widget.repository.revokeAgencyReportShare(
        _activeUuid,
      );
      if (mounted) setState(() => _share = share);
      _notify('أُلغي الرابط ولم يعد يفتح لدى أحد.');
    } catch (error) {
      _notify(error.toString());
    } finally {
      if (mounted) setState(() => _sharing = false);
    }
  }

  void _notify(String message) {
    if (!mounted) return;
    ScaffoldMessenger.of(
      context,
    ).showSnackBar(SnackBar(content: Text(message)));
  }

  Future<void> _regenerate(AgencyReportDetail report) async {
    setState(() => _regenerating = true);

    try {
      final updated = await widget.repository.generateAgencyReport(
        report.projectSlug,
        report.visibility,
      );
      if (!mounted) return;
      setState(() {
        _activeUuid = updated.uuid;
        _share = updated.share;
        _future = Future.value(updated);
      });
      _notify('أُنشئ إصدار محدث مع الاحتفاظ بإعدادات الخصوصية.');
    } catch (error) {
      _notify(error.toString());
    } finally {
      if (mounted) setState(() => _regenerating = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('مستند حالة المشروع')),
      body: FutureBuilder<AgencyReportDetail>(
        future: _future,
        builder: (context, snapshot) => AsyncView(
          snapshot: snapshot,
          onRetry: _reload,
          builder: (report) => ListView(
            padding: const EdgeInsets.all(16),
            children: _document(report),
          ),
        ),
      ),
    );
  }

  List<Widget> _document(AgencyReportDetail report) {
    final executive = report.executive;
    final share = _share ?? report.share;

    return [
      const Text(
        'نسخة ثابتة · يبني عليها فريق الوكالة مباشرة',
        style: TextStyle(color: BrandColors.muted),
      ),
      const SizedBox(height: 4),
      Text(
        report.title,
        style: const TextStyle(fontSize: 21, fontWeight: FontWeight.w700),
      ),
      if (report.freshness.isStale) ...[
        const SizedBox(height: 12),
        BrandCard(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text(
                'هذا الإصدار يحتاج تحديثًا',
                style: TextStyle(fontWeight: FontWeight.w700),
              ),
              const SizedBox(height: 6),
              for (final reason in report.freshness.reasons) Text('• $reason'),
              const SizedBox(height: 10),
              FilledButton.icon(
                onPressed: _regenerating ? null : () => _regenerate(report),
                icon: _regenerating
                    ? const SizedBox(
                        width: 18,
                        height: 18,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      )
                    : const Icon(Icons.refresh),
                label: const Text('أنشئ إصدارًا محدثًا'),
              ),
            ],
          ),
        ),
      ],
      const SizedBox(height: 14),
      BrandCard(
        child: report.readinessScore == null
            ? const Text(
                'لم تُسجَّل درجة جاهزية رقمية بعد؛ المستند يصف الحالة دون تقييم رقمي.',
                style: TextStyle(color: BrandColors.muted),
              )
            : BigScore(
                score: report.readinessScore!,
                band: report.readinessBand,
              ),
      ),
      const SizedBox(height: 12),
      FilledButton.icon(
        onPressed: _downloading ? null : _download,
        icon: _downloading
            ? const SizedBox(
                width: 18,
                height: 18,
                child: CircularProgressIndicator(strokeWidth: 2),
              )
            : const Icon(Icons.picture_as_pdf_outlined),
        label: const Text('حمّل PDF للتسليم'),
      ),
      const SizedBox(height: 12),
      _shareCard(share),
      if (report.decisionCard != null) ...[
        const SizedBox(height: 20),
        _heading('بطاقة القرار'),
        _decisionCard(report.decisionCard!),
      ],
      if (report.numbers.isNotEmpty) ...[
        const SizedBox(height: 20),
        _heading('الوضع الحالي بالأرقام'),
        if (report.trackingLabel != null)
          Padding(
            padding: const EdgeInsets.only(bottom: 8),
            child: Text(
              'نضج التتبع: ${report.trackingLabel}',
              style: const TextStyle(color: BrandColors.muted),
            ),
          ),
        BrandCard(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              for (final row in report.numbers)
                Padding(
                  padding: const EdgeInsets.only(bottom: 8),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        row.label,
                        style: const TextStyle(fontWeight: FontWeight.w600),
                      ),
                      Text(row.display),
                      Text(
                        row.benchmark == null
                            ? row.confidenceLabel
                            : '${row.confidenceLabel} · مرجع السوق: ${row.benchmark}',
                        style: const TextStyle(
                          color: BrandColors.muted,
                          fontSize: 11,
                        ),
                      ),
                    ],
                  ),
                ),
            ],
          ),
        ),
      ],
      if (report.assets.isNotEmpty) ...[
        const SizedBox(height: 20),
        _heading('الأصول والوصول'),
        BrandCard(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              for (final asset in report.assets)
                Padding(
                  padding: const EdgeInsets.only(bottom: 8),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        asset.label,
                        style: const TextStyle(fontWeight: FontWeight.w600),
                      ),
                      Text(
                        asset.display,
                        style: TextStyle(
                          color: asset.isDeclared ? null : BrandColors.muted,
                        ),
                      ),
                    ],
                  ),
                ),
            ],
          ),
        ),
      ],
      if (report.behaviourSummary != null) ...[
        const SizedBox(height: 20),
        _heading('سجل التنفيذ'),
        BrandCard(child: Text(report.behaviourSummary!)),
      ],
      if (executive != null) ...[
        const SizedBox(height: 20),
        _heading('الملخص التنفيذي'),
        BrandCard(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(executive.position),
              if (executive.context.isNotEmpty) ...[
                const SizedBox(height: 6),
                Text(
                  executive.context,
                  style: const TextStyle(color: BrandColors.muted),
                ),
              ],
              const SizedBox(height: 6),
              Text(
                'تغطية المعرفة الموثقة: ${executive.coveragePercent}٪',
                style: const TextStyle(color: BrandColors.muted),
              ),
            ],
          ),
        ),
        const SizedBox(height: 16),
        _heading('أبرز ما يحتاج معالجة'),
        if (executive.problems.isEmpty)
          const Text(
            'لم تُسجَّل مشكلات ذات خطورة.',
            style: TextStyle(color: BrandColors.muted),
          ),
        for (final problem in executive.problems) _itemCard(problem),
        const SizedBox(height: 16),
        _heading('أسرع ما يمكن البدء به'),
        if (executive.opportunities.isEmpty)
          const Text(
            'لا توجد مكاسب سريعة مسجّلة بعد.',
            style: TextStyle(color: BrandColors.muted),
          ),
        for (final item in executive.opportunities) _itemCard(item),
      ],
      if (report.consultation != null) ...[
        const SizedBox(height: 20),
        _heading('سياق الاستشارة الذكية'),
        _consultationCard(report.consultation!),
      ],
      if (report.crossTool.findings.isNotEmpty) ...[
        const SizedBox(height: 20),
        _heading('مقارنة نتائج التشخيصات'),
        ..._crossToolCards(report.crossTool),
      ],
      if (report.ledgerThemes.isNotEmpty) ...[
        const SizedBox(height: 20),
        _heading('حالة المشروع كما وثّقها صاحبه'),
        const Text(
          'يغني الوكالة عن إعادة جلسة الاستكشاف من الصفر.',
          style: TextStyle(color: BrandColors.muted),
        ),
        const SizedBox(height: 10),
        for (final theme in report.ledgerThemes) _themeCard(theme),
      ],
      const SizedBox(height: 20),
      _heading('التشخيصات المضمّنة ونتائجها'),
      for (final tool in report.tools) ...[
        BrandCard(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Eyebrow(tool.scoreLabel),
              const SizedBox(height: 5),
              Text(
                tool.title,
                style: const TextStyle(fontWeight: FontWeight.w700),
              ),
              const SizedBox(height: 5),
              Text(
                tool.summary,
                style: const TextStyle(color: BrandColors.muted),
              ),
              if (tool.review != null) ...[
                const SizedBox(height: 5),
                Text(
                  '${tool.review} · ${tool.producedAt ?? ''}',
                  style: const TextStyle(
                    color: BrandColors.muted,
                    fontSize: 12,
                  ),
                ),
              ],
            ],
          ),
        ),
        const SizedBox(height: 10),
      ],
      const SizedBox(height: 12),
      _heading('الأولويات التنفيذية'),
      for (final priority in report.priorities) ...[
        BrandCard(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Eyebrow(priority.sourceTool),
              const SizedBox(height: 4),
              Text(
                priority.title,
                style: const TextStyle(fontWeight: FontWeight.w700),
              ),
              const SizedBox(height: 5),
              Text(priority.description),
              if (priority.evidence != null) ...[
                const SizedBox(height: 6),
                Text(
                  'الدليل: ${priority.evidence}',
                  style: const TextStyle(
                    color: BrandColors.muted,
                    fontSize: 12,
                  ),
                ),
              ],
            ],
          ),
        ),
        const SizedBox(height: 10),
      ],
      const SizedBox(height: 12),
      _heading('خطة 30 / 60 / 90 يومًا'),
      for (final entry in const [
        ('30_days', 'أول 30 يومًا'),
        ('60_days', 'حتى 60 يومًا'),
        ('90_days', 'حتى 90 يومًا'),
      ]) ...[
        BrandCard(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                entry.$2,
                style: const TextStyle(fontWeight: FontWeight.w700),
              ),
              const SizedBox(height: 6),
              for (final item in report.plan(entry.$1))
                Padding(
                  padding: const EdgeInsets.only(bottom: 4),
                  child: Text('• ${item.title}'),
                ),
            ],
          ),
        ),
        const SizedBox(height: 10),
      ],
      const SizedBox(height: 12),
      _heading('مؤشرات الأداء وخط الأساس'),
      BrandCard(
        child: report.kpis.isEmpty
            ? const Text(
                'لم يُسجَّل أي مؤشر بخط أساس بعد. تثبيت مؤشر واحد مُدرج في خطة الأفق الأول.',
                style: TextStyle(color: BrandColors.muted),
              )
            : Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  for (final kpi in report.kpis)
                    Padding(
                      padding: const EdgeInsets.only(bottom: 8),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            '${kpi.name} ${kpi.unit ?? ''}'.trim(),
                            style: const TextStyle(fontWeight: FontWeight.w700),
                          ),
                          Text(
                            'خط الأساس: ${kpi.baseline ?? '—'} · الهدف: ${kpi.target ?? '—'} · آخر قراءة: ${kpi.latest ?? 'لم تُسجَّل'}',
                            style: const TextStyle(
                              color: BrandColors.muted,
                              fontSize: 12,
                            ),
                          ),
                        ],
                      ),
                    ),
                ],
              ),
      ),
      const SizedBox(height: 12),
      _heading('المنافسون'),
      BrandCard(
        child: _disclosure(
          report.competitors,
          (item) => item['name']?.toString() ?? '',
        ),
      ),
      const SizedBox(height: 12),
      _heading('سجل الأدلة'),
      BrandCard(child: _disclosure(report.evidence, (item) => item.toString())),
      const SizedBox(height: 12),
      BrandCard(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text(
              'النطاق والملكية',
              style: TextStyle(fontSize: 17, fontWeight: FontWeight.w700),
            ),
            const SizedBox(height: 8),
            Text(report.scope['account_ownership']?.toString() ?? ''),
            const SizedBox(height: 6),
            Text(
              report.scope['review_cadence']?.toString() ?? '',
              style: const TextStyle(color: BrandColors.muted),
            ),
          ],
        ),
      ),
      const SizedBox(height: 12),
      BrandCard(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text(
              'ما تحتاج الوكالة توضيحه قبل البدء',
              style: TextStyle(fontSize: 17, fontWeight: FontWeight.w700),
            ),
            const SizedBox(height: 8),
            for (final question in report.agencyQuestions) Text('• $question'),
          ],
        ),
      ),
      if (report.assumptions.isNotEmpty || report.dataGaps.isNotEmpty) ...[
        const SizedBox(height: 12),
        BrandCard(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text(
                'حدود المعرفة',
                style: TextStyle(fontSize: 17, fontWeight: FontWeight.w700),
              ),
              const SizedBox(height: 8),
              for (final item in report.assumptions) Text('• $item'),
              for (final item in report.dataGaps) Text('• بيان ناقص: $item'),
            ],
          ),
        ),
      ],
      if (report.methodologyLimits.isNotEmpty) ...[
        const SizedBox(height: 12),
        BrandCard(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text(
                'ملحق المنهجية',
                style: TextStyle(fontSize: 17, fontWeight: FontWeight.w700),
              ),
              const SizedBox(height: 8),
              for (final limit in report.methodologyLimits)
                Padding(
                  padding: const EdgeInsets.only(bottom: 4),
                  child: Text(
                    '• $limit',
                    style: const TextStyle(
                      color: BrandColors.muted,
                      fontSize: 12,
                    ),
                  ),
                ),
            ],
          ),
        ),
      ],
      const SizedBox(height: 24),
    ];
  }

  Widget _decisionCard(AgencyDecisionCard card) {
    return BrandCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Eyebrow('تُقرأ في تسعين ثانية'),
          const SizedBox(height: 4),
          Text(
            card.project,
            style: const TextStyle(fontSize: 17, fontWeight: FontWeight.w700),
          ),
          if (card.context.isNotEmpty)
            Text(
              card.context,
              style: const TextStyle(color: BrandColors.muted, fontSize: 12),
            ),
          const SizedBox(height: 8),
          Text(card.readiness),
          Text(
            card.trend ?? 'قياس واحد فقط حتى الآن — لا يمكن الحكم على الاتجاه.',
            style: const TextStyle(color: BrandColors.muted, fontSize: 12),
          ),
          const SizedBox(height: 8),
          Text('ما يصل إلى الإعلان: ${card.money ?? 'غير محسوب'}'),
          Text('تعريف النجاح: ${card.successMetric ?? 'لم يُكتب بعد'}'),
          Text(
            'معرفة ${card.knowledgePercent}٪ · أرقام ${card.numbersKnown}/${card.numbersTotal}'
            ' · أصول ${card.assetsPercent}٪',
            style: const TextStyle(color: BrandColors.muted, fontSize: 12),
          ),
          const SizedBox(height: 8),
          const Text(
            'ثلاث إشارات',
            style: TextStyle(fontWeight: FontWeight.w700),
          ),
          Text('• أعلى فرصة: ${card.opportunity ?? 'لم تُرشَّح بعد'}'),
          Text('• أكبر خطر: ${card.risk ?? 'لم تُسجَّل'}'),
          Text('• أكبر مجهول: ${card.unknown ?? 'لا مجهول جوهري'}'),
        ],
      ),
    );
  }

  Widget _consultationCard(AgencyConsultationContext consultation) {
    return BrandCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text('عمق الاستشارة: ${consultation.depth}'),
          if (consultation.inferences.isNotEmpty) ...[
            const SizedBox(height: 10),
            const Text(
              'استنتاجات تحتاج انتباهًا',
              style: TextStyle(fontWeight: FontWeight.w700),
            ),
            for (final item in consultation.inferences)
              Text('• ${item.statement} · ثقة ${item.confidence}٪'),
          ],
          if (consultation.conflicts.isNotEmpty) ...[
            const SizedBox(height: 10),
            const Text(
              'التعارضات وقرارات حسمها',
              style: TextStyle(fontWeight: FontWeight.w700),
            ),
            for (final item in consultation.conflicts)
              Text(
                '• ${item.message}${item.resolution == null ? '' : ' — ${item.resolution}'}',
              ),
          ],
          if (consultation.evidence.isNotEmpty) ...[
            const SizedBox(height: 10),
            const Text(
              'الأدلة المستخدمة',
              style: TextStyle(fontWeight: FontWeight.w700),
            ),
            for (final item in consultation.evidence) ...[
              Text('• ${item.name} · ${item.extractionLabel}'),
              if (item.text != null && item.text!.isNotEmpty)
                Text(
                  item.text!,
                  style: const TextStyle(
                    color: BrandColors.muted,
                    fontSize: 12,
                  ),
                ),
            ],
          ],
        ],
      ),
    );
  }

  List<Widget> _crossToolCards(AgencyCrossToolSynthesis synthesis) => [
    if (synthesis.agreements.isNotEmpty)
      BrandCard(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text(
              'نتائج متوافقة',
              style: TextStyle(fontWeight: FontWeight.w700),
            ),
            const SizedBox(height: 6),
            for (final item in synthesis.agreements)
              Text('• ${item.category}: ${item.findings.join('، ')}'),
          ],
        ),
      ),
    if (synthesis.divergences.isNotEmpty) ...[
      const SizedBox(height: 10),
      BrandCard(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text(
              'اختلاف يحتاج حسمًا',
              style: TextStyle(fontWeight: FontWeight.w700),
            ),
            const SizedBox(height: 6),
            for (final item in synthesis.divergences) ...[
              Text('• ${item.category}: ${item.findings.join('، ')}'),
              if (item.resolution != null)
                Text(
                  item.resolution!,
                  style: const TextStyle(
                    color: BrandColors.muted,
                    fontSize: 12,
                  ),
                ),
            ],
          ],
        ),
      ),
    ],
    const SizedBox(height: 10),
    BrandCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'النتائج ومصادرها',
            style: TextStyle(fontWeight: FontWeight.w700),
          ),
          const SizedBox(height: 6),
          for (final item in synthesis.findings)
            Text(
              '• ${item.title} — ${item.sourceToolTitle} (تقرير ${item.sourceReportId})',
            ),
        ],
      ),
    ),
  ];

  Widget _shareCard(AgencyShare share) {
    return BrandCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'مشاركة المستند مع وكالة',
            style: TextStyle(fontSize: 17, fontWeight: FontWeight.w700),
          ),
          const SizedBox(height: 8),
          if (share.isLive) ...[
            SelectableText(
              share.url ?? '',
              style: const TextStyle(fontSize: 12),
            ),
            const SizedBox(height: 6),
            Text(
              'فُتح ${share.viewsCount} مرة من ${share.uniqueViewers} جهة'
              '${share.expiresAt == null ? '' : ' · ينتهي ${share.expiresAt!.split('T').first}'}',
              style: const TextStyle(color: BrandColors.muted, fontSize: 12),
            ),
            const SizedBox(height: 8),
            Row(
              children: [
                OutlinedButton.icon(
                  onPressed: share.url == null
                      ? null
                      : () {
                          Clipboard.setData(ClipboardData(text: share.url!));
                          _notify('نُسخ الرابط.');
                        },
                  icon: const Icon(Icons.copy_outlined, size: 18),
                  label: const Text('انسخ'),
                ),
                const SizedBox(width: 8),
                TextButton(
                  onPressed: _sharing ? null : _revokeShare,
                  child: const Text('ألغِ الرابط'),
                ),
              ],
            ),
          ] else ...[
            const Text(
              'أنشئ رابطًا محدود المدة بدل إرسال الملف يدويًا. يمكنك إلغاؤه في أي لحظة، وكل فتحة تُسجَّل لك.',
              style: TextStyle(color: BrandColors.muted),
            ),
            const SizedBox(height: 8),
            OutlinedButton.icon(
              onPressed: _sharing
                  ? null
                  : () => _createShare(share.expiryChoices),
              icon: const Icon(Icons.link_outlined, size: 18),
              label: const Text('أنشئ رابط مشاركة'),
            ),
          ],
        ],
      ),
    );
  }

  Widget _disclosure(AgencyDisclosure block, String Function(dynamic) label) {
    if (!block.isFull || block.items.isEmpty) {
      return Text(
        block.notice,
        style: const TextStyle(color: BrandColors.muted),
      );
    }

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [for (final item in block.items) Text('• ${label(item)}')],
    );
  }

  Widget _themeCard(AgencyLedgerTheme theme) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: BrandCard(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Eyebrow('${theme.coveragePercent}٪'),
            const SizedBox(height: 4),
            Text(
              theme.title,
              style: const TextStyle(fontWeight: FontWeight.w700),
            ),
            Text(
              theme.intent,
              style: const TextStyle(color: BrandColors.muted, fontSize: 12),
            ),
            const SizedBox(height: 8),
            for (final entry in theme.answered)
              Padding(
                padding: const EdgeInsets.only(bottom: 6),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      entry.label,
                      style: const TextStyle(fontWeight: FontWeight.w600),
                    ),
                    Text(entry.value),
                  ],
                ),
              ),
            if (theme.unanswered.isNotEmpty)
              Text(
                'لم يُجب بعد: ${theme.unanswered.join('، ')}',
                style: const TextStyle(color: BrandColors.muted, fontSize: 12),
              ),
          ],
        ),
      ),
    );
  }

  Widget _itemCard(AgencyExecutiveItem item) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: BrandCard(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Eyebrow(item.sourceTool),
            const SizedBox(height: 4),
            Text(
              item.title,
              style: const TextStyle(fontWeight: FontWeight.w700),
            ),
            const SizedBox(height: 5),
            Text(item.description),
            if (item.note != null && item.note!.isNotEmpty) ...[
              const SizedBox(height: 5),
              Text(
                item.note!,
                style: const TextStyle(color: BrandColors.muted, fontSize: 12),
              ),
            ],
          ],
        ),
      ),
    );
  }

  Widget _heading(String text) => Padding(
    padding: const EdgeInsets.only(bottom: 10),
    child: Text(
      text,
      style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w700),
    ),
  );
}
