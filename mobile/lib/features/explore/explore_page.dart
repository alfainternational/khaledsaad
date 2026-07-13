import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../app/routes/app_routes.dart';
import '../../core/error/api_exception.dart';
import '../../data/repositories/public_repository.dart';
import '../shared/widgets/animated_app_background.dart';
import '../shared/widgets/app_state_view.dart';
import '../shared/widgets/brand_mark.dart';

/// الواجهات العامة (تجربة الضيف): الرئيسية، الأدوات، القوالب، الباقات —
/// نفس محتوى صفحات الويب العامة، متاحة قبل تسجيل الدخول، بدعوة دائمة للبدء.
class ExplorePage extends StatefulWidget {
  const ExplorePage({super.key});

  @override
  State<ExplorePage> createState() => _ExplorePageState();
}

class _ExplorePageState extends State<ExplorePage> {
  late final PublicRepository _repo = Get.find<PublicRepository>();

  final _data = Rxn<Map<String, dynamic>>();
  final _loading = true.obs;
  final _error = RxnString();
  final _tab = 0.obs;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    _loading.value = true;
    _error.value = null;
    try {
      _data.value = await _repo.overview();
    } on ApiException catch (e) {
      _error.value = e.message;
    } finally {
      _loading.value = false;
    }
  }

  List<Map<String, dynamic>> _listOf(String key) {
    final raw = _data.value?[key];
    if (raw is! List) return const [];
    return raw
        .whereType<Map>()
        .map((e) => Map<String, dynamic>.from(e))
        .toList();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('استكشف المنصة'),
        actions: [
          TextButton(
            onPressed: () => Get.toNamed(Routes.login),
            child: const Text('دخول'),
          ),
        ],
      ),
      bottomNavigationBar: Obx(() => NavigationBar(
            selectedIndex: _tab.value,
            onDestinationSelected: (i) => _tab.value = i,
            destinations: const [
              NavigationDestination(
                  icon: Icon(Icons.home_outlined), label: 'الرئيسية'),
              NavigationDestination(
                  icon: Icon(Icons.build_outlined), label: 'الأدوات'),
              NavigationDestination(
                  icon: Icon(Icons.auto_awesome_outlined), label: 'القوالب'),
              NavigationDestination(
                  icon: Icon(Icons.workspace_premium_outlined),
                  label: 'الباقات'),
            ],
          )),
      body: AnimatedAppBackground(
        child: Obx(() {
          if (_loading.value) return AppStateView.loading();
          if (_error.value != null && _data.value == null) {
            return AppStateView.error(message: _error.value, onRetry: _load);
          }
          return RefreshIndicator(
            onRefresh: _load,
            child: switch (_tab.value) {
              1 => _ToolsTab(stages: _listOf('stages')),
              2 => _TemplatesTab(templates: _listOf('templates')),
              3 => _PlansTab(plans: _listOf('plans')),
              _ => _HomeTab(
                  hero: _data.value?['hero'] is Map
                      ? Map<String, dynamic>.from(_data.value?['hero'] as Map)
                      : const {},
                  paths: _listOf('paths'),
                  blog: _listOf('blog'),
                  caseStudies: _listOf('case_studies'),
                ),
            },
          );
        }),
      ),
    );
  }
}

/// دعوة البدء الموحدة أسفل كل تبويب.
class _StartCta extends StatelessWidget {
  const _StartCta({this.label = 'ابدأ مجاناً الآن'});

  final String label;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 16),
      child: FilledButton.icon(
        onPressed: () => Get.toNamed(Routes.register),
        icon: const Icon(Icons.arrow_back),
        label: Text(label),
      ),
    );
  }
}

class _SectionTitle extends StatelessWidget {
  const _SectionTitle(this.text);

  final String text;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(top: 18, bottom: 8),
      child: Text(
        text,
        style: Theme.of(context)
            .textTheme
            .titleMedium
            ?.copyWith(fontWeight: FontWeight.w900),
      ),
    );
  }
}

class _HomeTab extends StatelessWidget {
  const _HomeTab({
    required this.hero,
    required this.paths,
    required this.blog,
    required this.caseStudies,
  });

  final Map<String, dynamic> hero;
  final List<Map<String, dynamic>> paths;
  final List<Map<String, dynamic>> blog;
  final List<Map<String, dynamic>> caseStudies;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return ListView(
      padding: const EdgeInsets.fromLTRB(16, 16, 16, 28),
      children: [
        const BrandMark(size: 56),
        const SizedBox(height: 16),
        Text(
          hero['title']?.toString() ?? 'منصة التسويق الاستراتيجي',
          style: theme.textTheme.headlineSmall
              ?.copyWith(fontWeight: FontWeight.w900, height: 1.35),
        ),
        const SizedBox(height: 8),
        Text(
          hero['subtitle']?.toString() ?? '',
          style: theme.textTheme.bodyLarge?.copyWith(
            height: 1.7,
            color: theme.colorScheme.onSurfaceVariant,
          ),
        ),
        const _StartCta(),
        const _SectionTitle('المسارات — من أين تبدأ؟'),
        for (final path in paths)
          Card(
            margin: const EdgeInsets.only(bottom: 8),
            child: ListTile(
              leading:
                  Icon(Icons.route_outlined, color: theme.colorScheme.primary),
              title: Text(path['label']?.toString() ?? '',
                  style: theme.textTheme.titleSmall
                      ?.copyWith(fontWeight: FontWeight.w800)),
              subtitle: Text(path['description']?.toString() ?? ''),
            ),
          ),
        if (caseStudies.isNotEmpty) ...[
          const _SectionTitle('قصص نجاح'),
          for (final case_ in caseStudies)
            Card(
              margin: const EdgeInsets.only(bottom: 8),
              child: ListTile(
                leading: Icon(Icons.emoji_events_outlined,
                    color: theme.colorScheme.primary),
                title: Text(case_['title']?.toString() ?? ''),
                subtitle: Text(case_['summary']?.toString() ?? ''),
              ),
            ),
        ],
        if (blog.isNotEmpty) ...[
          const _SectionTitle('من المدونة'),
          for (final post in blog)
            Card(
              margin: const EdgeInsets.only(bottom: 8),
              child: ListTile(
                leading: Icon(Icons.article_outlined,
                    color: theme.colorScheme.primary),
                title: Text(post['title']?.toString() ?? ''),
                subtitle: Text(post['excerpt']?.toString() ?? ''),
              ),
            ),
        ],
      ],
    );
  }
}

class _ToolsTab extends StatelessWidget {
  const _ToolsTab({required this.stages});

  final List<Map<String, dynamic>> stages;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return ListView(
      padding: const EdgeInsets.fromLTRB(16, 16, 16, 28),
      children: [
        Text(
          'رحلة من خمس مراحل تقودك خطوة بخطوة',
          style: theme.textTheme.titleLarge
              ?.copyWith(fontWeight: FontWeight.w900),
        ),
        const SizedBox(height: 4),
        Text('كل مرحلة تفتح أدواتها حسب ما يحتاجه مشروعك.',
            style: theme.textTheme.bodyMedium),
        const SizedBox(height: 12),
        for (final stage in stages) ...[
          _SectionTitle(
              'المرحلة ${stage['number']}: ${stage['label'] ?? ''}'),
          if ((stage['description']?.toString() ?? '').isNotEmpty)
            Padding(
              padding: const EdgeInsets.only(bottom: 8),
              child: Text(stage['description'].toString(),
                  style: theme.textTheme.bodySmall),
            ),
          for (final tool in (stage['tools'] as List? ?? const []))
            if (tool is Map)
              Card(
                margin: const EdgeInsets.only(bottom: 6),
                child: ListTile(
                  dense: true,
                  leading: Icon(Icons.build_circle_outlined,
                      color: theme.colorScheme.primary),
                  title: Text(tool['name']?.toString() ?? ''),
                  subtitle: Text(tool['description']?.toString() ?? ''),
                  trailing: ((tool['estimated_minutes'] as num?) ?? 0) > 0
                      ? Text('${tool['estimated_minutes']} د',
                          style: theme.textTheme.labelSmall)
                      : null,
                ),
              ),
        ],
        const _StartCta(label: 'جرّب الأدوات مجاناً'),
      ],
    );
  }
}

class _TemplatesTab extends StatelessWidget {
  const _TemplatesTab({required this.templates});

  final List<Map<String, dynamic>> templates;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return ListView(
      padding: const EdgeInsets.fromLTRB(16, 16, 16, 28),
      children: [
        Text(
          'قوالب الاستوديو الذكي',
          style: theme.textTheme.titleLarge
              ?.copyWith(fontWeight: FontWeight.w900),
        ),
        const SizedBox(height: 4),
        Text(
          'مخرجات تسويقية جاهزة للاستخدام يولّدها الذكاء من بيانات مشروعك: إعلانات، إيميلات، خطط محتوى، سكربتات بيع...',
          style: theme.textTheme.bodyMedium?.copyWith(height: 1.6),
        ),
        const SizedBox(height: 12),
        for (final template in templates)
          Card(
            margin: const EdgeInsets.only(bottom: 8),
            child: ListTile(
              leading: Icon(Icons.auto_awesome,
                  color: theme.colorScheme.primary),
              title: Text(template['name']?.toString() ?? '',
                  style: theme.textTheme.titleSmall
                      ?.copyWith(fontWeight: FontWeight.w700)),
              subtitle: Text(template['description']?.toString() ?? ''),
            ),
          ),
        const _StartCta(label: 'ولّد أول مخرج مجاناً'),
      ],
    );
  }
}

class _PlansTab extends StatelessWidget {
  const _PlansTab({required this.plans});

  final List<Map<String, dynamic>> plans;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return ListView(
      padding: const EdgeInsets.fromLTRB(16, 16, 16, 28),
      children: [
        Text(
          'الباقات والأسعار',
          style: theme.textTheme.titleLarge
              ?.copyWith(fontWeight: FontWeight.w900),
        ),
        const SizedBox(height: 12),
        for (final plan in plans)
          Card(
            margin: const EdgeInsets.only(bottom: 10),
            child: Padding(
              padding: const EdgeInsets.all(14),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Expanded(
                        child: Text(plan['name']?.toString() ?? '',
                            style: theme.textTheme.titleMedium
                                ?.copyWith(fontWeight: FontWeight.w900)),
                      ),
                      Text(
                        ((plan['monthly_price'] as num?) ?? 0) > 0
                            ? '\$${plan['monthly_price']} / شهر'
                            : 'مجانية',
                        style: theme.textTheme.titleSmall?.copyWith(
                          color: theme.colorScheme.primary,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 8),
                  for (final feature in (plan['features'] as List? ?? const []))
                    Padding(
                      padding: const EdgeInsets.only(bottom: 4),
                      child: Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Icon(Icons.check,
                              size: 16, color: theme.colorScheme.primary),
                          const SizedBox(width: 6),
                          Expanded(
                              child: Text(feature.toString(),
                                  style: theme.textTheme.bodySmall)),
                        ],
                      ),
                    ),
                ],
              ),
            ),
          ),
        const _StartCta(label: 'ابدأ بالباقة المجانية'),
      ],
    );
  }
}
