import 'package:flutter/material.dart';

import '../../core/api/platform_repository.dart';
import '../../core/theme/app_theme.dart';
import '../../core/widgets/common.dart';
import '../account/billing_screen.dart';
import '../account/notifications_screen.dart';
import '../tools/engagement.dart';
import '../tools/models.dart';
import '../tools/resume_navigator.dart';
import '../tools/tool_catalog_screen.dart';
import 'models.dart';
import 'project_form_screen.dart';
import 'project_screen.dart';

typedef _DashboardData = (List<ProjectCard>, List<ToolCard>, List<ResumeCard>);

/// يقابل resources/views/app/dashboard.blade.php
class DashboardScreen extends StatefulWidget {
  const DashboardScreen({super.key, required this.repository, required this.onLogout});

  final PlatformRepository repository;
  final VoidCallback onLogout;

  @override
  State<DashboardScreen> createState() => _DashboardScreenState();
}

class _DashboardScreenState extends State<DashboardScreen> {
  late Future<_DashboardData> _future;

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  Future<_DashboardData> _load() async {
    final projects = await widget.repository.projects();
    final tools = await widget.repository.tools();
    final unfinished = await widget.repository.unfinished();

    return (projects, tools.where((tool) => tool.isRunnable).take(4).toList(), unfinished);
  }

  void _reload() => setState(() => _future = _load());

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('مشاريعك'),
        actions: [
          IconButton(
            tooltip: 'الإشعارات',
            icon: const Icon(Icons.notifications_none),
            onPressed: () => Navigator.of(context).push(
              MaterialPageRoute(
                builder: (_) => NotificationsScreen(repository: widget.repository),
              ),
            ),
          ),
          IconButton(
            tooltip: 'الأرصدة',
            icon: const Icon(Icons.account_balance_wallet_outlined),
            onPressed: () => Navigator.of(context).push(
              MaterialPageRoute(
                builder: (_) => BillingScreen(repository: widget.repository),
              ),
            ),
          ),
          IconButton(
            tooltip: 'بماذا نساعدك',
            icon: const Icon(Icons.grid_view_outlined),
            onPressed: () => Navigator.of(context).push(
              MaterialPageRoute(
                builder: (_) => ToolCatalogScreen(repository: widget.repository),
              ),
            ),
          ),
          IconButton(
            tooltip: 'خروج',
            icon: const Icon(Icons.logout),
            onPressed: () async {
              await widget.repository.logout();
              widget.onLogout();
            },
          ),
        ],
      ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () async {
          final created = await Navigator.of(context).push<bool>(
            MaterialPageRoute(
              builder: (_) => ProjectFormScreen(repository: widget.repository),
            ),
          );

          if (created == true) _reload();
        },
        icon: const Icon(Icons.add),
        label: const Text('مشروع جديد'),
      ),
      body: FutureBuilder<_DashboardData>(
        future: _future,
        builder: (context, snapshot) => AsyncView(
          snapshot: snapshot,
          onRetry: _reload,
          builder: (data) {
            final (projects, tools, unfinished) = data;

            return RefreshIndicator(
              onRefresh: () async => _reload(),
              child: ListView(
                padding: const EdgeInsets.fromLTRB(16, 16, 16, 96),
                children: [
                  // طريق العودة: من ترك شيئًا في المنتصف يجده أول ما يفتح التطبيق.
                  if (unfinished.isNotEmpty) ...[
                    const Text('أكمل ما بدأته',
                        style: TextStyle(fontSize: 18, fontWeight: FontWeight.w700)),
                    const SizedBox(height: 12),
                    for (final card in unfinished) ...[
                      _ResumeTile(
                        card: card,
                        onTap: () async {
                          await ResumeNavigator.openCard(context, widget.repository, card);
                          _reload();
                        },
                      ),
                      const SizedBox(height: 10),
                    ],
                    const SizedBox(height: 12),
                  ],

                  if (projects.isEmpty)
                    const EmptyState(
                      title: 'ما عرّفتنا على مشروعك بعد',
                      message: 'عرّفنا على مشروعك مرة واحدة، وبعدها كل خطوة تقرأ منه ولا تسألك من جديد.',
                    )
                  else ...[
                    const Text('مشاريعك',
                        style: TextStyle(fontSize: 18, fontWeight: FontWeight.w700)),
                    const SizedBox(height: 12),
                    for (final project in projects) ...[
                      BrandCard(
                        onTap: () async {
                          await Navigator.of(context).push(
                            MaterialPageRoute(
                              builder: (_) => ProjectScreen(
                                repository: widget.repository,
                                slug: project.slug,
                              ),
                            ),
                          );
                          _reload();
                        },
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(project.name,
                                style: const TextStyle(fontSize: 17, fontWeight: FontWeight.w700)),
                            const SizedBox(height: 8),
                            if (project.latestScore != null)
                              ScoreChip(label: '${project.latestScore}/100 · ${project.scoreBand}')
                            else
                              const Text('ما بدأنا فيه بعد',
                                  style: TextStyle(color: BrandColors.muted)),
                            const SizedBox(height: 6),
                            Text(project.industry ?? 'قطاع غير محدد',
                                style: const TextStyle(color: BrandColors.muted, fontSize: 13)),
                          ],
                        ),
                      ),
                      const SizedBox(height: 12),
                    ],
                  ],

                  const SizedBox(height: 20),
                  const Text('ابدأ من هنا',
                      style: TextStyle(fontSize: 18, fontWeight: FontWeight.w700)),
                  const SizedBox(height: 12),
                  for (final tool in tools) ...[
                    BrandCard(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Eyebrow(tool.category),
                          const SizedBox(height: 4),
                          Text(tool.title,
                              style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w700)),
                          const SizedBox(height: 6),
                          Text(tool.headline,
                              style: const TextStyle(color: BrandColors.muted, fontSize: 13)),
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
}

class _ResumeTile extends StatelessWidget {
  const _ResumeTile({required this.card, required this.onTap});

  final ResumeCard card;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Card(
      clipBehavior: Clip.antiAlias,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(20),
        side: const BorderSide(color: BrandColors.blue),
      ),
      child: InkWell(
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.all(18),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(card.toolTitle,
                  style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w700)),
              const SizedBox(height: 4),
              Text(
                '${card.projectName ?? ''} · ${card.hint ?? ''}',
                style: const TextStyle(color: BrandColors.muted, fontSize: 13),
              ),
              if (card.isDraft && card.percent > 0) ...[
                const SizedBox(height: 10),
                ClipRRect(
                  borderRadius: BorderRadius.circular(999),
                  child: LinearProgressIndicator(value: card.percent / 100, minHeight: 6),
                ),
              ],
              const SizedBox(height: 12),
              FilledButton(onPressed: onTap, child: Text(card.label)),
            ],
          ),
        ),
      ),
    );
  }
}
