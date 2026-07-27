import 'package:flutter/material.dart';

import '../../core/api/platform_repository.dart';
import '../../core/theme/app_theme.dart';
import '../../core/widgets/common.dart';
import '../agency_reports/agency_reports_screen.dart';
import '../consultations/consultation_screen.dart';
import '../growth/growth_hub_screen.dart';
import '../reports/report_screen.dart';
import '../tools/models.dart';
import '../tools/run_wizard_screen.dart';
import 'models.dart';
import 'tasks_screen.dart';

/// يقابل resources/views/app/projects/show.blade.php
class ProjectScreen extends StatefulWidget {
  const ProjectScreen({
    super.key,
    required this.repository,
    required this.slug,
  });

  final PlatformRepository repository;
  final String slug;

  @override
  State<ProjectScreen> createState() => _ProjectScreenState();
}

class _ProjectScreenState extends State<ProjectScreen> {
  late Future<(ProjectOverview, List<ToolCard>)> _future;

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  Future<(ProjectOverview, List<ToolCard>)> _load() async {
    final project = await widget.repository.project(widget.slug);
    final tools = await widget.repository.tools();

    return (project, tools);
  }

  void _reload() => setState(() => _future = _load());

  Future<void> _startRun(String toolKey) async {
    try {
      final run = await widget.repository.startRun(widget.slug, toolKey);

      if (!mounted) return;

      await Navigator.of(context).push(
        MaterialPageRoute(
          builder: (_) =>
              RunWizardScreen(repository: widget.repository, run: run),
        ),
      );

      _reload();
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(error.toString())));
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('المشروع')),
      body: FutureBuilder<(ProjectOverview, List<ToolCard>)>(
        future: _future,
        builder: (context, snapshot) => AsyncView(
          snapshot: snapshot,
          onRetry: _reload,
          builder: (data) {
            final (project, tools) = data;

            return RefreshIndicator(
              onRefresh: () async => _reload(),
              child: ListView(
                padding: const EdgeInsets.all(16),
                children: [
                  FilledButton.icon(
                    onPressed: () => Navigator.of(context).push(
                      MaterialPageRoute(
                        builder: (_) => ConsultationScreen(
                          repository: widget.repository,
                          projectSlug: widget.slug,
                        ),
                      ),
                    ),
                    icon: const Icon(Icons.auto_awesome),
                    label: const Text('ابدأ تشخيص مشروعك'),
                  ),
                  const SizedBox(height: 16),
                  Text(
                    project.card.name,
                    style: const TextStyle(
                      fontSize: 22,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  Text(
                    project.card.industry ?? 'قطاع غير محدد',
                    style: const TextStyle(color: BrandColors.muted),
                  ),
                  const SizedBox(height: 16),

                  BrandCard(
                    child: project.card.latestScore == null
                        ? const Column(
                            children: [
                              Eyebrow('درجة الجاهزية'),
                              SizedBox(height: 8),
                              Text(
                                'لم تُحتسب بعد. ابدأ تشخيص الجاهزية لعرض الدرجة والأولويات هنا.',
                                textAlign: TextAlign.center,
                                style: TextStyle(color: BrandColors.muted),
                              ),
                            ],
                          )
                        : BigScore(
                            score: project.card.latestScore!,
                            band: project.card.scoreBand ?? '',
                            delta: project.comparison?.label,
                          ),
                  ),
                  const SizedBox(height: 12),

                  BrandCard(
                    onTap: () => Navigator.of(context).push(
                      MaterialPageRoute(
                        builder: (_) => TasksScreen(
                          repository: widget.repository,
                          slug: widget.slug,
                          projectName: project.card.name,
                        ),
                      ),
                    ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Eyebrow('التنفيذ'),
                        const SizedBox(height: 8),
                        _row('مهام مفتوحة', '${project.openTasks}'),
                        _row('مهام متأخرة', '${project.overdueTasks}'),
                        _row('مهام منجزة', '${project.doneTasks}'),
                      ],
                    ),
                  ),
                  const SizedBox(height: 20),

                  BrandCard(
                    onTap: () => Navigator.of(context).push(
                      MaterialPageRoute(
                        builder: (_) => AgencyReportsScreen(
                          repository: widget.repository,
                          projectSlug: widget.slug,
                          projectName: project.card.name,
                        ),
                      ),
                    ),
                    child: const Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Eyebrow('للتسليم والمقارنة'),
                        SizedBox(height: 5),
                        Text(
                          'موجز الوكالة الموحّد',
                          style: TextStyle(
                            fontSize: 17,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                        SizedBox(height: 5),
                        Text(
                          'اجمع أحدث نتائج أدواتك في نسخة ثابتة وPDF جاهز للوكالات.',
                          style: TextStyle(color: BrandColors.muted),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 20),

                  BrandCard(
                    onTap: () => Navigator.of(context).push(
                      MaterialPageRoute(
                        builder: (_) => GrowthHubScreen(
                          repository: widget.repository,
                          projectSlug: widget.slug,
                          projectName: project.card.name,
                        ),
                      ),
                    ),
                    child: const Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Eyebrow('التحسين المستمر'),
                        SizedBox(height: 5),
                        Text(
                          'فرص إضافية لتحسين المشروع',
                          style: TextStyle(
                            fontSize: 17,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                        SizedBox(height: 5),
                        Text(
                          'النبض الأسبوعي، الظهور في محركات الإجابة، الجمهور، ومؤشرات القياس.',
                          style: TextStyle(color: BrandColors.muted),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 20),

                  const Text(
                    'التقارير',
                    style: TextStyle(fontSize: 18, fontWeight: FontWeight.w700),
                  ),
                  const SizedBox(height: 10),

                  if (project.reports.isEmpty)
                    const Text(
                      'لا توجد تقارير بعد. ابدأ أحد التشخيصات لإنشاء التقرير الأول.',
                      style: TextStyle(color: BrandColors.muted),
                    )
                  else
                    for (final report in project.reports) ...[
                      BrandCard(
                        onTap: () => Navigator.of(context).push(
                          MaterialPageRoute(
                            builder: (_) => ReportScreen(
                              repository: widget.repository,
                              reportId: report.id,
                            ),
                          ),
                        ),
                        child: Row(
                          children: [
                            Expanded(child: Text(report.title)),
                            ScoreChip(label: '${report.score}/100'),
                          ],
                        ),
                      ),
                      const SizedBox(height: 10),
                    ],

                  const SizedBox(height: 20),
                  const Text(
                    'المؤشرات',
                    style: TextStyle(fontSize: 18, fontWeight: FontWeight.w700),
                  ),
                  const SizedBox(height: 10),

                  if (project.kpis.isEmpty)
                    const Text(
                      'لا مؤشرات بعد. المؤشر هو ما يثبت أن المهام غيّرت شيئًا فعلًا.',
                      style: TextStyle(color: BrandColors.muted),
                    )
                  else
                    for (final kpi in project.kpis) ...[
                      BrandCard(
                        child: Row(
                          children: [
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text(
                                    kpi.name,
                                    style: const TextStyle(
                                      fontWeight: FontWeight.w600,
                                    ),
                                  ),
                                  Text(
                                    '${kpi.latest ?? '—'} ${kpi.unit ?? ''}',
                                    style: const TextStyle(
                                      color: BrandColors.muted,
                                    ),
                                  ),
                                ],
                              ),
                            ),
                            if (kpi.attainmentPercent != null)
                              ScoreChip(
                                label: '${kpi.attainmentPercent}% من الهدف',
                              ),
                          ],
                        ),
                      ),
                      const SizedBox(height: 10),
                    ],

                  const SizedBox(height: 20),
                  const Text(
                    'ابدأ تشخيصًا لهذا المشروع',
                    style: TextStyle(fontSize: 18, fontWeight: FontWeight.w700),
                  ),
                  const SizedBox(height: 10),

                  for (final tool in tools) ...[
                    BrandCard(
                      muted: !tool.isRunnable,
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Eyebrow(tool.category),
                          const SizedBox(height: 4),
                          Text(
                            tool.title,
                            style: const TextStyle(
                              fontSize: 16,
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                          const SizedBox(height: 6),
                          Text(
                            tool.description,
                            style: const TextStyle(
                              color: BrandColors.muted,
                              fontSize: 13,
                            ),
                          ),
                          const SizedBox(height: 12),
                          if (tool.isRunnable)
                            FilledButton(
                              onPressed: () => _startRun(tool.key),
                              child: const Text('ابدأ التشخيص'),
                            )
                          else
                            const SeverityBadge(
                              label: 'قريبًا',
                              severity: 'low',
                            ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 12),
                  ],
                ],
              ),
            );
          },
        ),
      ),
    );
  }

  Widget _row(String label, String value) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 5),
    child: Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        Text(label, style: const TextStyle(color: BrandColors.muted)),
        Text(value, style: const TextStyle(fontWeight: FontWeight.w700)),
      ],
    ),
  );
}
