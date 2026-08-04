import 'package:flutter/material.dart';

import '../../core/api/api_exception.dart';
import '../../core/api/platform_repository.dart';
import '../../core/theme/app_theme.dart';
import '../../core/widgets/common.dart';
import '../tools/models.dart';
import '../tools/run_wizard_screen.dart';
import 'public_content_screen.dart';
import 'public_info_screen.dart';
import 'public_profile_screen.dart';
import 'public_tool_screen.dart';

class PublicShell extends StatefulWidget {
  const PublicShell({
    super.key,
    required this.repository,
    this.onLogin,
    this.onRegister,
  });

  final PlatformRepository repository;
  final VoidCallback? onLogin;
  final VoidCallback? onRegister;

  @override
  State<PublicShell> createState() => _PublicShellState();
}

class _PublicShellState extends State<PublicShell> {
  late Future<Map<String, dynamic>> _future = widget.repository
      .publicBootstrap();
  int _index = 0;
  bool _starting = false;

  Future<void> _startTrial(ToolCard tool) async {
    if (_starting) return;
    setState(() => _starting = true);
    try {
      final run = await widget.repository.startGuestRun(tool.key);
      if (!mounted) return;
      await Navigator.of(context).push(
        MaterialPageRoute(
          builder: (_) => RunWizardScreen(
            repository: widget.repository,
            run: run,
            guest: true,
            onGuestCompleted: () {
              Navigator.of(context).pop();
              widget.onRegister?.call();
            },
          ),
        ),
      );
    } on ApiException catch (exception) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(exception.message)));
      }
    } finally {
      if (mounted) setState(() => _starting = false);
    }
  }

  void _openTool(ToolCard tool) {
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => PublicToolScreen(
          repository: widget.repository,
          toolKey: tool.key,
          onStart: _startTrial,
          onLogin: widget.onLogin ?? () {},
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) => FutureBuilder<Map<String, dynamic>>(
    future: _future,
    builder: (context, snapshot) {
      final data = snapshot.data ?? const <String, dynamic>{};
      final brand = data['brand'] is Map
          ? Map<String, dynamic>.from(data['brand'] as Map)
          : _fallbackBrand;
      final tools = (data['tools'] as List? ?? const [])
          .whereType<Map>()
          .map((item) => ToolCard.fromJson(Map<String, dynamic>.from(item)))
          .toList();
      final links = data['links'] is Map
          ? Map<String, dynamic>.from(data['links'] as Map)
          : const <String, dynamic>{};

      return Scaffold(
        appBar: AppBar(
          title: Text(_titles[_index]),
          actions: [
            if (widget.onLogin != null)
              TextButton(
                onPressed: widget.onLogin,
                child: const Text('تسجيل الدخول'),
              ),
          ],
        ),
        body: snapshot.connectionState == ConnectionState.waiting
            ? const Center(child: CircularProgressIndicator())
            : IndexedStack(
                index: _index,
                children: [
                  _PublicOverview(
                    brand: brand,
                    loadFailed: snapshot.hasError,
                    onRetry: () => setState(
                      () => _future = widget.repository.publicBootstrap(),
                    ),
                    onOpen: (index) => setState(() => _index = index),
                    onRegister: widget.onRegister,
                  ),
                  PublicContentScreen(repository: widget.repository),
                  _PublicTools(
                    tools: tools,
                    starting: _starting,
                    onOpenTool: _openTool,
                  ),
                  PublicProfileScreen(
                    brand: brand,
                    profilePdfUrl: links['profile_pdf']?.toString(),
                  ),
                  PublicInfoScreen(brand: brand),
                ],
              ),
        bottomNavigationBar: PublicNavigationBar(
          currentIndex: _index,
          onDestinationSelected: (value) => setState(() => _index = value),
        ),
      );
    },
  );
}

const _titles = ['خالد سعد', 'المعرفة', 'التشخيصات', 'السيرة', 'المزيد'];

const _fallbackBrand = <String, dynamic>{
  'name': 'خالد سعد',
  'headline': 'اعرف أين يتعطّل تسويقك، وبم تبدأ قبل أن تزيد الوقت أو الميزانية',
  'tagline':
      'أجب عن أسئلة واضحة، واحصل على أولويات يمكنك تنفيذها أو مشاركتها مع فريقك ووكالتك.',
  'about': <String>[],
  'experience': <Map<String, dynamic>>[],
  'education': <Map<String, dynamic>>[],
  'credentials': <Map<String, dynamic>>[],
  'skills': <String>[],
  'professional_services': <String>[],
  'problems': <Map<String, dynamic>>[],
  'services': <Map<String, dynamic>>[],
  'method': <Map<String, dynamic>>[],
  'principles': <Map<String, dynamic>>[],
  'faqs': <Map<String, dynamic>>[],
  'contact': <String, dynamic>{},
};

class PublicNavigationBar extends StatelessWidget {
  const PublicNavigationBar({
    super.key,
    required this.currentIndex,
    required this.onDestinationSelected,
  });

  final int currentIndex;
  final ValueChanged<int> onDestinationSelected;

  @override
  Widget build(BuildContext context) => NavigationBar(
    selectedIndex: currentIndex,
    onDestinationSelected: onDestinationSelected,
    destinations: const [
      NavigationDestination(icon: Icon(Icons.home_outlined), label: 'الرئيسية'),
      NavigationDestination(
        icon: Icon(Icons.menu_book_outlined),
        label: 'المعرفة',
      ),
      NavigationDestination(
        icon: Icon(Icons.grid_view_outlined),
        label: 'الأدوات',
      ),
      NavigationDestination(icon: Icon(Icons.badge_outlined), label: 'السيرة'),
      NavigationDestination(icon: Icon(Icons.more_horiz), label: 'المزيد'),
    ],
  );
}

class _PublicOverview extends StatelessWidget {
  const _PublicOverview({
    required this.brand,
    required this.loadFailed,
    required this.onRetry,
    required this.onOpen,
    required this.onRegister,
  });

  final Map<String, dynamic> brand;
  final bool loadFailed;
  final VoidCallback onRetry;
  final ValueChanged<int> onOpen;
  final VoidCallback? onRegister;

  @override
  Widget build(BuildContext context) {
    final about = (brand['about'] as List? ?? const [])
        .map((item) => item.toString())
        .toList();
    return ListView(
      key: const PageStorageKey('public-overview'),
      padding: const EdgeInsets.fromLTRB(16, 16, 16, 100),
      children: [
        Container(
          padding: const EdgeInsets.all(22),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(24),
            gradient: const LinearGradient(
              colors: [BrandColors.navy, Color(0xFF123F91)],
            ),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Image.asset(
                'assets/brand/khaled-saad-approved.png',
                width: 108,
                height: 72,
                fit: BoxFit.contain,
              ),
              const SizedBox(height: 12),
              const Text(
                'ابدأ من واقع مشروعك',
                style: TextStyle(color: BrandColors.cyan, fontSize: 13),
              ),
              const SizedBox(height: 8),
              Text(
                brand['headline']?.toString() ?? '',
                style: const TextStyle(
                  color: Colors.white,
                  fontSize: 27,
                  height: 1.35,
                  fontWeight: FontWeight.w800,
                ),
              ),
              const SizedBox(height: 10),
              Text(
                brand['tagline']?.toString() ?? '',
                style: const TextStyle(color: Color(0xFFD9E8FF), height: 1.6),
              ),
              const SizedBox(height: 18),
              FilledButton(
                onPressed: () => onOpen(2),
                child: const Text('ابدأ تشخيص مشروعك'),
              ),
              if (onRegister != null) ...[
                const SizedBox(height: 8),
                OutlinedButton(
                  style: OutlinedButton.styleFrom(
                    foregroundColor: Colors.white,
                    side: const BorderSide(color: Color(0xFFB9D2FB)),
                  ),
                  onPressed: onRegister,
                  child: const Text('أنشئ حسابك واحفظ تقدمك'),
                ),
              ],
            ],
          ),
        ),
        if (loadFailed) ...[
          const SizedBox(height: 14),
          ErrorNotice(
            message: 'تعذر تحديث معلومات المنصة. تحقق من الاتصال.',
            onRetry: onRetry,
          ),
        ],
        const SizedBox(height: 18),
        if (about.isNotEmpty)
          BrandCard(
            onTap: () => onOpen(3),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Eyebrow('عني'),
                const SizedBox(height: 6),
                Text(
                  brand['professional_headline']?.toString() ?? '',
                  style: const TextStyle(
                    fontSize: 19,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 8),
                Text(
                  about.first,
                  maxLines: 4,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(color: BrandColors.muted, height: 1.6),
                ),
                const SizedBox(height: 8),
                const Text(
                  'افتح السيرة المهنية الكاملة ←',
                  style: TextStyle(
                    color: BrandColors.blue,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ],
            ),
          ),
        const SizedBox(height: 12),
        Row(
          children: [
            Expanded(
              child: _DestinationCard(
                icon: Icons.menu_book_outlined,
                title: 'المعرفة',
                subtitle: 'المقالات والدروس',
                onTap: () => onOpen(1),
              ),
            ),
            const SizedBox(width: 10),
            Expanded(
              child: _DestinationCard(
                icon: Icons.grid_view_outlined,
                title: 'الأدوات',
                subtitle: 'ابدأ التشخيص',
                onTap: () => onOpen(2),
              ),
            ),
          ],
        ),
        const SizedBox(height: 10),
        _DestinationCard(
          icon: Icons.route_outlined,
          title: 'الخدمات والمنهجية والأسئلة',
          subtitle: 'كل ما كان موزعًا في الصفحة الرئيسية أصبح متاحًا هنا.',
          onTap: () => onOpen(4),
        ),
      ],
    );
  }
}

class _DestinationCard extends StatelessWidget {
  const _DestinationCard({
    required this.icon,
    required this.title,
    required this.subtitle,
    required this.onTap,
  });

  final IconData icon;
  final String title;
  final String subtitle;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => BrandCard(
    onTap: onTap,
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Icon(icon, color: BrandColors.blue),
        const SizedBox(height: 8),
        Text(title, style: const TextStyle(fontWeight: FontWeight.w800)),
        const SizedBox(height: 3),
        Text(
          subtitle,
          style: const TextStyle(color: BrandColors.muted, fontSize: 13),
        ),
      ],
    ),
  );
}

class _PublicTools extends StatelessWidget {
  const _PublicTools({
    required this.tools,
    required this.starting,
    required this.onOpenTool,
  });

  final List<ToolCard> tools;
  final bool starting;
  final ValueChanged<ToolCard> onOpenTool;

  @override
  Widget build(BuildContext context) => ListView(
    key: const PageStorageKey('public-tools'),
    padding: const EdgeInsets.fromLTRB(16, 20, 16, 100),
    children: [
      const Eyebrow('اختر الأولوية'),
      const SizedBox(height: 6),
      const Text(
        'ما الذي تريد فهمه أو تحسينه الآن؟',
        style: TextStyle(fontSize: 24, fontWeight: FontWeight.w800),
      ),
      const SizedBox(height: 16),
      if (starting) const LinearProgressIndicator(),
      for (final tool in tools) ...[
        BrandCard(
          onTap: starting ? null : () => onOpenTool(tool),
          muted: !tool.isRunnable,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Eyebrow(tool.category),
              const SizedBox(height: 5),
              Text(
                tool.title,
                style: const TextStyle(
                  fontSize: 17,
                  fontWeight: FontWeight.w800,
                ),
              ),
              const SizedBox(height: 6),
              Text(tool.headline),
              const SizedBox(height: 8),
              Text(
                tool.isRunnable ? 'اعرف التفاصيل وابدأ ←' : tool.statusLabel,
                style: const TextStyle(color: BrandColors.blue),
              ),
            ],
          ),
        ),
        const SizedBox(height: 10),
      ],
    ],
  );
}
