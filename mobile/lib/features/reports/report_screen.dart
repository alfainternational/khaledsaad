import 'dart:io';

import 'package:flutter/material.dart';
import 'package:open_filex/open_filex.dart';
import 'package:path_provider/path_provider.dart';

import '../../core/api/platform_repository.dart';
import '../../core/theme/app_theme.dart';
import '../../core/widgets/common.dart';
import 'competitors_card.dart';
import 'models.dart';
import 'report_charts.dart';

/// يقابل resources/views/app/reports/show.blade.php
///
/// النقطة الجوهرية المنقولة حرفيًا: الفصل الظاهر بين ما يستند إلى دليل
/// وما هو افتراض. هذا ليس تفصيلًا بصريًا بل قاعدة منتج (BR-007).
class ReportScreen extends StatefulWidget {
  const ReportScreen({super.key, required this.repository, required this.reportId});

  final PlatformRepository repository;
  final int reportId;

  @override
  State<ReportScreen> createState() => _ReportScreenState();
}

class _ReportScreenState extends State<ReportScreen> {
  late Future<ReportDetail> _future = widget.repository.report(widget.reportId);
  bool _converting = false;
  bool _downloadingPdf = false;

  Future<void> _downloadPdf() async {
    setState(() => _downloadingPdf = true);

    try {
      final bytes = await widget.repository.reportPdf(widget.reportId);
      final dir = await getTemporaryDirectory();
      final file = File('${dir.path}/report-${widget.reportId}.pdf');
      await file.writeAsBytes(bytes, flush: true);

      await OpenFilex.open(file.path);
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(error.toString())));
      }
    } finally {
      if (mounted) setState(() => _downloadingPdf = false);
    }
  }

  void _reload() => setState(() => _future = widget.repository.report(widget.reportId));

  Future<void> _convert({int? recommendationId}) async {
    setState(() => _converting = true);

    try {
      final tasks = await widget.repository.convertRecommendations(
        widget.reportId,
        recommendationId: recommendationId,
      );

      if (!mounted) return;

      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('${tasks.length} توصية أصبحت مهامًا بمواعيد نهائية.')),
      );

      _reload();
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(error.toString())));
      }
    } finally {
      if (mounted) setState(() => _converting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('التقرير')),
      body: FutureBuilder<ReportDetail>(
        future: _future,
        builder: (context, snapshot) => AsyncView(
          snapshot: snapshot,
          onRetry: _reload,
          builder: (report) => ListView(
            padding: const EdgeInsets.all(16),
            children: [
              Text('${report.toolTitle} · ${report.projectName}',
                  style: const TextStyle(color: BrandColors.muted)),
              const SizedBox(height: 6),
              Text(report.title,
                  style: const TextStyle(fontSize: 20, fontWeight: FontWeight.w700)),
              const SizedBox(height: 16),

              BrandCard(child: BigScore(score: report.score, band: report.scoreBand)),
              const SizedBox(height: 12),

              BrandCard(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Eyebrow('الخلاصة'),
                    const SizedBox(height: 8),
                    Text(report.summary),
                    if (report.nextStepTitle != null) ...[
                      const SizedBox(height: 16),
                      Container(
                        width: double.infinity,
                        padding: const EdgeInsets.all(14),
                        decoration: BoxDecoration(
                          color: BrandColors.surfaceSoft,
                          borderRadius: BorderRadius.circular(13),
                        ),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            const Eyebrow('الخطوة التالية'),
                            const SizedBox(height: 4),
                            Text(report.nextStepTitle!,
                                style: const TextStyle(fontWeight: FontWeight.w700)),
                            const SizedBox(height: 4),
                            Text(report.nextStepDescription ?? '',
                                style: const TextStyle(color: BrandColors.muted)),
                          ],
                        ),
                      ),
                    ],
                  ],
                ),
              ),
              const SizedBox(height: 12),

              // المؤشرات البصرية: نفس بيانات الويب والـPDF (report.charts).
              if (report.charts != null) ...[
                ReportChartsSection(charts: report.charts!),
                const SizedBox(height: 4),
              ],

              Text(
                'أُنتج بالنموذج ${report.model ?? 'غير مسجل'} · إصدار الأداة ${report.toolVersion ?? '—'} · '
                '${report.evidenceBacked} نتيجة مدعومة بدليل و${report.assumptionCount} افتراضًا.',
                style: const TextStyle(color: BrandColors.muted, fontSize: 12),
              ),
              const SizedBox(height: 16),

              FilledButton.icon(
                onPressed: _converting ? null : () => _convert(),
                icon: const Icon(Icons.checklist),
                label: const Text('حوّل أهم 3 توصيات إلى مهام'),
              ),
              const SizedBox(height: 10),
              OutlinedButton.icon(
                onPressed: _downloadingPdf ? null : _downloadPdf,
                icon: _downloadingPdf
                    ? const SizedBox(
                        height: 18, width: 18, child: CircularProgressIndicator(strokeWidth: 2))
                    : const Icon(Icons.picture_as_pdf_outlined),
                label: const Text('حمّل PDF'),
              ),

              if (report.assumptions.isNotEmpty) ...[
                const SizedBox(height: 18),
                BrandCard(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text('ما لم يُتحقق منه',
                          style: TextStyle(fontSize: 16, fontWeight: FontWeight.w700)),
                      const SizedBox(height: 8),
                      for (final assumption in report.assumptions) Text('• $assumption'),
                    ],
                  ),
                ),
              ],

              const SizedBox(height: 20),
              const Text('النتائج والتوصيات',
                  style: TextStyle(fontSize: 18, fontWeight: FontWeight.w700)),
              const SizedBox(height: 12),

              if (report.findings.isEmpty)
                const Text(
                  'لم تُنتَج نتائج موسعة في هذه المحاولة. الدرجة وإجاباتك محفوظة، ويمكنك إعادة طلب التحليل.',
                  style: TextStyle(color: BrandColors.muted),
                )
              else
                for (final finding in report.findings) ...[
                  _buildFinding(finding),
                  const SizedBox(height: 12),
                ],

              const SizedBox(height: 20),
              CompetitorsCard(
                repository: widget.repository,
                projectSlug: report.projectSlug,
              ),

              const SizedBox(height: 20),
              const Text('تفاصيل التحليل',
                  style: TextStyle(fontSize: 18, fontWeight: FontWeight.w700)),
              const SizedBox(height: 12),

              for (final section in report.sections) _buildSection(section),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildFinding(FindingModel finding) => BrandCard(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Wrap(
              spacing: 8,
              runSpacing: 6,
              crossAxisAlignment: WrapCrossAlignment.center,
              children: [
                SeverityBadge(label: finding.severityLabel, severity: finding.severity),
                SeverityBadge(
                  label: finding.basisLabel,
                  severity: finding.isAssumption ? 'assumption' : 'low',
                ),
              ],
            ),
            const SizedBox(height: 8),
            Text(finding.title,
                style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w700)),
            const SizedBox(height: 6),
            Text(finding.description),

            if (finding.evidence != null) ...[
              const SizedBox(height: 10),
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: BrandColors.surfaceSoft,
                  border: const Border(
                    right: BorderSide(color: BrandColors.cyan, width: 3),
                  ),
                  borderRadius: BorderRadius.circular(10),
                ),
                child: Text('الدليل: ${finding.evidence}',
                    style: const TextStyle(fontSize: 13)),
              ),
            ],

            const SizedBox(height: 12),
            for (final recommendation in finding.recommendations) ...[
              Container(
                width: double.infinity,
                margin: const EdgeInsets.only(bottom: 10),
                padding: const EdgeInsets.all(14),
                decoration: BoxDecoration(
                  border: Border.all(color: BrandColors.line),
                  borderRadius: BorderRadius.circular(13),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(recommendation.title,
                        style: const TextStyle(fontWeight: FontWeight.w700)),
                    const SizedBox(height: 4),
                    Text(recommendation.description,
                        style: const TextStyle(color: BrandColors.muted, fontSize: 13)),
                    const SizedBox(height: 8),
                    Wrap(
                      spacing: 6,
                      runSpacing: 6,
                      children: [
                        SeverityBadge(label: recommendation.impactLabel, severity: 'low'),
                        SeverityBadge(label: recommendation.effortLabel, severity: 'low'),
                        if (recommendation.kpiHint != null)
                          SeverityBadge(
                            label: 'المؤشر: ${recommendation.kpiHint}',
                            severity: 'low',
                          ),
                      ],
                    ),
                    const SizedBox(height: 10),
                    if (recommendation.isTask)
                      const SeverityBadge(label: 'أصبحت مهمة', severity: 'low')
                    else
                      OutlinedButton(
                        onPressed: _converting
                            ? null
                            : () => _convert(recommendationId: recommendation.id),
                        child: const Text('حوّلها إلى مهمة'),
                      ),
                  ],
                ),
              ),
            ],
          ],
        ),
      );

  Widget _buildSection(ReportSectionModel section) => Card(
        margin: const EdgeInsets.only(bottom: 10),
        child: ExpansionTile(
          shape: const Border(),
          title: Text(section.title, style: const TextStyle(fontWeight: FontWeight.w600)),
          childrenPadding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
          children: [
            if (section.key == 'score')
              Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(section.content['method']?.toString() ?? '',
                      style: const TextStyle(color: BrandColors.muted, fontSize: 12)),
                  const SizedBox(height: 10),
                  for (final row in section.breakdown)
                    Padding(
                      padding: const EdgeInsets.symmetric(vertical: 3),
                      child: Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          Text(row['label']?.toString() ?? '',
                              style: const TextStyle(color: BrandColors.muted)),
                          Text('${row['points']} / ${row['weight']}',
                              style: const TextStyle(fontWeight: FontWeight.w600)),
                        ],
                      ),
                    ),
                ],
              )
            else
              Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  if (section.headline != null)
                    Padding(
                      padding: const EdgeInsets.only(bottom: 10),
                      child: Text(section.headline!,
                          style: const TextStyle(fontSize: 15, fontWeight: FontWeight.w600)),
                    ),
                  for (final point in section.points)
                    Padding(
                      padding: const EdgeInsets.symmetric(vertical: 4),
                      child: Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          const Text('• '),
                          Expanded(child: Text(point['text']?.toString() ?? '')),
                          if (point['is_assumption'] == true) ...[
                            const SizedBox(width: 6),
                            const SeverityBadge(label: 'افتراض', severity: 'assumption'),
                          ],
                        ],
                      ),
                    ),
                ],
              ),
          ],
        ),
      );
}
