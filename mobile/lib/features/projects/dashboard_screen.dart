import 'package:flutter/material.dart';

import '../../core/api/platform_repository.dart';
import '../../core/firebase/firebase_service.dart';
import '../../core/theme/app_theme.dart';
import '../../core/widgets/adaptive_layout.dart';
import '../../core/widgets/common.dart';
import '../account/billing_screen.dart';
import '../account/notifications_screen.dart';
import '../admin/admin_hub_screen.dart';
import '../consultations/consultation_screen.dart';
import '../consultations/consultations_list_screen.dart';
import '../experience/experience_selection_screen.dart';
import '../growth/pulse_screen.dart';
import '../portfolio/portfolio_screen.dart';
import '../public/public_shell.dart';
import '../tools/engagement.dart';
import '../tools/models.dart';
import '../tools/resume_navigator.dart';
import '../tools/tool_catalog_screen.dart';
import 'models.dart';
import 'project_form_screen.dart';
import 'project_screen.dart';

typedef _DashboardData = (
  List<ProjectCard>,
  List<ToolCard>,
  List<ResumeCard>,
  bool,
);

/// يقابل resources/views/app/dashboard.blade.php
class DashboardScreen extends StatefulWidget {
  const DashboardScreen({
    super.key,
    required this.repository,
    required this.onLogout,
    this.onExperienceChanged,
  });

  final PlatformRepository repository;
  final VoidCallback onLogout;
  final VoidCallback? onExperienceChanged;

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
    final account = await widget.repository.me();

    return (
      projects,
      tools.where((tool) => tool.isRunnable).take(4).toList(),
      unfinished,
      account['is_admin'] == true,
    );
  }

  void _reload() => setState(() => _future = _load());

  @override
  Widget build(BuildContext context) {
    return AdaptiveScaffold(
      family: AdaptivePageFamily.operational,
      appBar: AppBar(
        title: const Text('لوحة التحكم'),
        actions: [
          IconButton(
            tooltip: 'تغيير ما أعمل عليه الآن',
            icon: const Icon(Icons.swap_horiz),
            onPressed: () async {
              final account = await widget.repository.me();
              if (!context.mounted) return;
              await Navigator.of(context).push(
                MaterialPageRoute(
                  builder: (_) => ExperienceSelectionScreen(
                    repository: widget.repository,
                    account: account,
                    onChanged: () => Navigator.of(context).pop(true),
                  ),
                ),
              );
              widget.onExperienceChanged?.call();
            },
          ),
          IconButton(
            tooltip: 'الإشعارات',
            icon: const Icon(Icons.notifications_none),
            onPressed: () => Navigator.of(context).push(
              MaterialPageRoute(
                builder: (_) =>
                    NotificationsScreen(repository: widget.repository),
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
            tooltip: 'التشخيصات',
            icon: const Icon(Icons.grid_view_outlined),
            onPressed: () => Navigator.of(context).push(
              MaterialPageRoute(
                builder: (_) =>
                    ToolCatalogScreen(repository: widget.repository),
              ),
            ),
          ),
          // وجهات مساحة العمل في قائمة واحدة بدل إغراق الشريط بالأيقونات.
          // من لا يملك ميزةً منها يقابل رسالة الترقية من الشاشة لا زرًّا محجوبًا.
          PopupMenuButton<String>(
            tooltip: 'المزيد',
            icon: const Icon(Icons.apps_outlined),
            onSelected: (value) {
              final screen = switch (value) {
                'consultations' => ConsultationsListScreen(
                  repository: widget.repository,
                ),
                'portfolio' => PortfolioScreen(repository: widget.repository),
                'pulse' => PulseScreen(repository: widget.repository),
                'public_hub' => PublicShell(repository: widget.repository),
                _ => null,
              };
              if (screen != null) {
                Navigator.of(
                  context,
                ).push(MaterialPageRoute(builder: (_) => screen));
              }
            },
            itemBuilder: (context) => const [
              PopupMenuItem(
                value: 'consultations',
                child: ListTile(
                  leading: Icon(Icons.forum_outlined),
                  title: Text('الاستشارات'),
                ),
              ),
              PopupMenuItem(
                value: 'portfolio',
                child: ListTile(
                  leading: Icon(Icons.business_center_outlined),
                  title: Text('محفظة العملاء'),
                ),
              ),
              PopupMenuItem(
                value: 'pulse',
                child: ListTile(
                  leading: Icon(Icons.monitor_heart_outlined),
                  title: Text('نبض النمو الأسبوعي'),
                ),
              ),
              PopupMenuItem(
                value: 'public_hub',
                child: ListTile(
                  leading: Icon(Icons.public_outlined),
                  title: Text('المعرفة والسيرة'),
                ),
              ),
            ],
          ),
          IconButton(
            tooltip: 'خروج',
            icon: const Icon(Icons.logout),
            onPressed: () async {
              await FirebaseService.instance.removeDevice(widget.repository);
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
        label: const Text('أضف مشروعًا'),
      ),
      body: FutureBuilder<_DashboardData>(
        future: _future,
        builder: (context, snapshot) => AsyncView(
          snapshot: snapshot,
          onRetry: _reload,
          builder: (data) {
            final (projects, tools, unfinished, isAdmin) = data;

            return RefreshIndicator(
              onRefresh: () async => _reload(),
              child: ListView(
                padding: const EdgeInsets.only(bottom: 72),
                children: [
                  if (isAdmin) ...[
                    BrandCard(
                      onTap: () => Navigator.of(context).push(
                        MaterialPageRoute(
                          builder: (_) =>
                              AdminHubScreen(repository: widget.repository),
                        ),
                      ),
                      child: const Row(
                        children: [
                          Icon(Icons.admin_panel_settings_outlined),
                          SizedBox(width: 12),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  'لوحة الإدارة',
                                  style: TextStyle(fontWeight: FontWeight.w700),
                                ),
                                Text(
                                  'المستخدمون والأدوات والبرومبتات والمدفوعات والإعدادات.',
                                  style: TextStyle(color: BrandColors.muted),
                                ),
                              ],
                            ),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 14),
                  ],
                  // طريق العودة: من ترك شيئًا في المنتصف يجده أول ما يفتح التطبيق.
                  if (unfinished.isNotEmpty) ...[
                    const Text(
                      'أكمل ما بدأته',
                      style: TextStyle(
                        fontSize: 18,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                    const SizedBox(height: 12),
                    for (final card in unfinished) ...[
                      _ResumeTile(
                        card: card,
                        onTap: () async {
                          await ResumeNavigator.openCard(
                            context,
                            widget.repository,
                            card,
                          );
                          _reload();
                        },
                      ),
                      const SizedBox(height: 10),
                    ],
                    const SizedBox(height: 12),
                  ],

                  if (projects.isEmpty)
                    const EmptyState(
                      title: 'أضف مشروعك الأول',
                      message:
                          'أدخل معلوماته الأساسية مرة واحدة لتخصيص الأسئلة والتقارير من دون تكرار.',
                    )
                  else ...[
                    const Text(
                      'مشاريعك',
                      style: TextStyle(
                        fontSize: 18,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
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
                            Text(
                              project.name,
                              style: const TextStyle(
                                fontSize: 17,
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                            const SizedBox(height: 8),
                            if (project.latestScore != null)
                              ScoreChip(
                                label:
                                    '${project.latestScore}/100 · ${project.scoreBand}',
                              )
                            else
                              const Text(
                                'لم يبدأ التشخيص بعد',
                                style: TextStyle(color: BrandColors.muted),
                              ),
                            const SizedBox(height: 6),
                            Text(
                              project.sectorLabel,
                              style: const TextStyle(
                                color: BrandColors.muted,
                                fontSize: 13,
                              ),
                            ),
                            const SizedBox(height: 8),
                            Align(
                              alignment: AlignmentDirectional.centerStart,
                              child: FilledButton.tonalIcon(
                                onPressed: () async {
                                  await Navigator.of(context).push(
                                    MaterialPageRoute(
                                      builder: (_) => ConsultationScreen(
                                        repository: widget.repository,
                                        projectSlug: project.slug,
                                      ),
                                    ),
                                  );
                                  _reload();
                                },
                                icon: const Icon(Icons.auto_awesome, size: 18),
                                label: const Text('ابدأ التشخيص'),
                              ),
                            ),
                          ],
                        ),
                      ),
                      const SizedBox(height: 12),
                    ],
                  ],

                  const SizedBox(height: 20),
                  const Text(
                    'تشخيصات مقترحة للبدء',
                    style: TextStyle(fontSize: 18, fontWeight: FontWeight.w700),
                  ),
                  const SizedBox(height: 12),
                  for (final tool in tools) ...[
                    BrandCard(
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
                            tool.headline,
                            style: const TextStyle(
                              color: BrandColors.muted,
                              fontSize: 13,
                            ),
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
              Text(
                card.toolTitle,
                style: const TextStyle(
                  fontSize: 16,
                  fontWeight: FontWeight.w700,
                ),
              ),
              const SizedBox(height: 4),
              Text(
                '${card.projectName ?? ''} · ${card.hint ?? ''}',
                style: const TextStyle(color: BrandColors.muted, fontSize: 13),
              ),
              if (card.isDraft && card.percent > 0) ...[
                const SizedBox(height: 10),
                ClipRRect(
                  borderRadius: BorderRadius.circular(999),
                  child: LinearProgressIndicator(
                    value: card.percent / 100,
                    minHeight: 6,
                  ),
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
