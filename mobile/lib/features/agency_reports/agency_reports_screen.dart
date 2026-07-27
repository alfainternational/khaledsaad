import 'package:flutter/material.dart';

import '../../core/api/platform_repository.dart';
import '../../core/theme/app_theme.dart';
import '../../core/widgets/adaptive_layout.dart';
import '../../core/widgets/common.dart';
import 'agency_report_screen.dart';
import 'models.dart';

class AgencyReportsScreen extends StatefulWidget {
  const AgencyReportsScreen({
    super.key,
    required this.repository,
    required this.projectSlug,
    required this.projectName,
  });

  final PlatformRepository repository;
  final String projectSlug;
  final String projectName;

  @override
  State<AgencyReportsScreen> createState() => _AgencyReportsScreenState();
}

class _AgencyReportsScreenState extends State<AgencyReportsScreen> {
  late Future<AgencyReportIndex> _future = widget.repository.agencyReports(
    widget.projectSlug,
  );
  bool _generating = false;
  final Map<String, String> _visibility = {
    'budget': 'full',
    'competitors': 'full',
    'evidence': 'full',
  };

  void _reload() {
    setState(
      () => _future = widget.repository.agencyReports(widget.projectSlug),
    );
  }

  Future<void> _generate() async {
    setState(() => _generating = true);

    try {
      final report = await widget.repository.generateAgencyReport(
        widget.projectSlug,
        _visibility,
      );

      if (!mounted) return;
      await Navigator.of(context).push(
        MaterialPageRoute(
          builder: (_) => AgencyReportScreen(
            repository: widget.repository,
            uuid: report.uuid,
            initial: report,
          ),
        ),
      );
      _reload();
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(error.toString())));
      }
    } finally {
      if (mounted) setState(() => _generating = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return AdaptiveScaffold(
      family: AdaptivePageFamily.operational,
      appBar: AppBar(title: const Text('موجز الوكالة')),
      body: FutureBuilder<AgencyReportIndex>(
        future: _future,
        builder: (context, snapshot) => AsyncView(
          snapshot: snapshot,
          onRetry: _reload,
          builder: (index) => ListView(
            padding: EdgeInsets.zero,
            children: [
              Text(
                widget.projectName,
                style: const TextStyle(
                  fontSize: 21,
                  fontWeight: FontWeight.w700,
                ),
              ),
              const SizedBox(height: 4),
              const Text(
                'نسخة ثابتة تجمع أحدث تشخيصات مشروعك لتسليمها إلى وكالة ومقارنة عروضها.',
                style: TextStyle(color: BrandColors.muted),
              ),
              const SizedBox(height: 16),
              BrandCard(
                child: index.readiness.canGenerate
                    ? Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          const Eyebrow('جاهز للإنشاء'),
                          const SizedBox(height: 6),
                          Text(
                            'سيُضمّن ${index.readiness.includedCount} تقارير بأحدث نتيجة صالحة من كل تشخيص.',
                          ),
                          const SizedBox(height: 12),
                          ExpansionTile(
                            tilePadding: EdgeInsets.zero,
                            shape: const Border(),
                            title: const Text('ما الذي يظهر للوكالة؟'),
                            children: [
                              _visibilityField('budget', 'الميزانية'),
                              _visibilityField('competitors', 'المنافسون'),
                              _visibilityField('evidence', 'الأدلة التفصيلية'),
                            ],
                          ),
                          const SizedBox(height: 10),
                          FilledButton(
                            onPressed: _generating ? null : _generate,
                            child: _generating
                                ? const SizedBox(
                                    width: 20,
                                    height: 20,
                                    child: CircularProgressIndicator(
                                      strokeWidth: 2,
                                      color: Colors.white,
                                    ),
                                  )
                                : const Text('أنشئ إصدارًا ثابتًا جديدًا'),
                          ),
                        ],
                      )
                    : Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          const Eyebrow('أكمل الأساس أولًا'),
                          const SizedBox(height: 6),
                          const Text('التشخيصات الناقصة:'),
                          const SizedBox(height: 6),
                          for (final tool in index.readiness.missingCore)
                            Text('• ${tool.title}'),
                        ],
                      ),
              ),
              const SizedBox(height: 20),
              const Text(
                'الإصدارات السابقة',
                style: TextStyle(fontSize: 18, fontWeight: FontWeight.w700),
              ),
              const SizedBox(height: 10),
              if (index.reports.isEmpty)
                const Text(
                  'لم يُنشأ موجز بعد.',
                  style: TextStyle(color: BrandColors.muted),
                )
              else
                for (final report in index.reports) ...[
                  BrandCard(
                    onTap: () => Navigator.of(context).push(
                      MaterialPageRoute(
                        builder: (_) => AgencyReportScreen(
                          repository: widget.repository,
                          uuid: report.uuid,
                        ),
                      ),
                    ),
                    child: Row(
                      children: [
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(report.title),
                              Text(
                                report.freshness.label,
                                style: TextStyle(
                                  color: report.freshness.isStale
                                      ? Colors.orange.shade800
                                      : BrandColors.muted,
                                  fontSize: 12,
                                ),
                              ),
                            ],
                          ),
                        ),
                        ScoreChip(label: 'الإصدار ${report.version}'),
                      ],
                    ),
                  ),
                  const SizedBox(height: 10),
                ],
            ],
          ),
        ),
      ),
    );
  }

  Widget _visibilityField(String key, String label) => Padding(
    padding: const EdgeInsets.only(bottom: 10),
    child: DropdownButtonFormField<String>(
      initialValue: _visibility[key],
      decoration: InputDecoration(labelText: label),
      items: const [
        DropdownMenuItem(value: 'full', child: Text('تظهر كاملة')),
        DropdownMenuItem(value: 'summary', child: Text('تظهر كملخص')),
        DropdownMenuItem(value: 'private', child: Text('تبقى داخلية')),
      ],
      onChanged: (value) {
        if (value != null) _visibility[key] = value;
      },
    ),
  );
}
