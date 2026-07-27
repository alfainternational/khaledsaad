import 'package:flutter/material.dart';

import '../../core/api/api_exception.dart';
import '../../core/api/platform_repository.dart';
import '../../core/theme/app_theme.dart';
import '../../core/widgets/adaptive_layout.dart';
import '../../core/widgets/common.dart';
import '../tools/models.dart';
import '../tools/run_wizard_screen.dart';
import 'legal_screen.dart';
import 'public_tool_screen.dart';

class PublicHomeScreen extends StatefulWidget {
  const PublicHomeScreen({
    super.key,
    required this.repository,
    required this.onLogin,
    required this.onRegister,
  });

  final PlatformRepository repository;
  final VoidCallback onLogin;
  final VoidCallback onRegister;

  @override
  State<PublicHomeScreen> createState() => _PublicHomeScreenState();
}

class _PublicHomeScreenState extends State<PublicHomeScreen> {
  late Future<Map<String, dynamic>> _future = widget.repository
      .publicBootstrap();
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
              widget.onRegister();
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

  void _openLegal(String page, String title) {
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => LegalScreen(
          repository: widget.repository,
          page: page,
          fallbackTitle: title,
        ),
      ),
    );
  }

  void _openTool(ToolCard tool) {
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => PublicToolScreen(
          repository: widget.repository,
          toolKey: tool.key,
          onStart: _startTrial,
          onLogin: widget.onLogin,
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return AdaptiveScaffold(
      family: AdaptivePageFamily.operational,
      appBar: AppBar(
        title: const Text('خالد سعد'),
        actions: [
          TextButton(
            onPressed: widget.onLogin,
            child: const Text('تسجيل الدخول'),
          ),
        ],
      ),
      body: FutureBuilder<Map<String, dynamic>>(
        future: _future,
        builder: (context, snapshot) {
          final data = snapshot.data ?? const <String, dynamic>{};
          final brand = Map<String, dynamic>.from(
            data['brand'] as Map? ?? const {},
          );
          final tools = (data['tools'] as List? ?? const [])
              .whereType<Map>()
              .map((item) => ToolCard.fromJson(Map<String, dynamic>.from(item)))
              .toList();
          final entryData = data['entry_tool'];
          final entry = entryData is Map
              ? ToolCard.fromJson(Map<String, dynamic>.from(entryData))
              : tools.where((tool) => tool.isRunnable).firstOrNull;

          return RefreshIndicator(
            onRefresh: () async {
              final next = widget.repository.publicBootstrap();
              setState(() => _future = next);
              await next;
            },
            child: ListView(
              padding: EdgeInsets.zero,
              children: [
                Center(
                  child: Image.asset(
                    'assets/brand/khaled-saad-approved.png',
                    width: 112,
                    height: 112,
                    fit: BoxFit.contain,
                  ),
                ),
                const SizedBox(height: 14),
                const Eyebrow('ابدأ من واقع مشروعك'),
                const SizedBox(height: 8),
                Text(
                  brand['headline']?.toString() ??
                      'اعرف أين تتعطل نتائج التسويق، وما الذي يستحق أن تبدأ به.',
                  style: const TextStyle(
                    fontSize: 27,
                    height: 1.35,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 10),
                Text(
                  brand['tagline']?.toString() ??
                      'أجب عن أسئلة واضحة، واحصل على أولويات يمكنك تنفيذها أو مشاركتها مع فريقك ووكالتك.',
                  style: const TextStyle(
                    color: BrandColors.muted,
                    fontSize: 16,
                  ),
                ),
                const SizedBox(height: 20),
                FilledButton.icon(
                  onPressed: entry == null || _starting
                      ? widget.onRegister
                      : () => _startTrial(entry),
                  icon: const Icon(Icons.auto_awesome),
                  label: Text(
                    _starting ? 'جارٍ تجهيز التشخيص…' : 'ابدأ تشخيص مشروعك',
                  ),
                ),
                const SizedBox(height: 10),
                OutlinedButton(
                  onPressed: widget.onRegister,
                  child: const Text('أنشئ حسابك واحفظ تقدمك'),
                ),
                const SizedBox(height: 28),
                const Text(
                  'ما الذي تريد تحسينه؟',
                  style: TextStyle(fontSize: 20, fontWeight: FontWeight.w700),
                ),
                const SizedBox(height: 12),
                if (snapshot.hasError)
                  BrandCard(
                    child: Column(
                      children: [
                        const Text(
                          'تعذر عرض التشخيصات الآن. تحقق من الاتصال وحاول مرة أخرى.',
                        ),
                        TextButton(
                          onPressed: () => setState(
                            () => _future = widget.repository.publicBootstrap(),
                          ),
                          child: const Text('إعادة المحاولة'),
                        ),
                      ],
                    ),
                  )
                else if (snapshot.connectionState == ConnectionState.waiting)
                  const Center(child: CircularProgressIndicator())
                else
                  for (final tool in tools) ...[
                    BrandCard(
                      onTap: () => _openTool(tool),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Eyebrow(tool.category),
                          const SizedBox(height: 5),
                          Text(
                            tool.title,
                            style: const TextStyle(
                              fontSize: 17,
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                          const SizedBox(height: 6),
                          Text(tool.headline),
                          const SizedBox(height: 8),
                          Text(
                            tool.isRunnable
                                ? 'اضغط لبدء التشخيص'
                                : tool.statusLabel,
                            style: const TextStyle(color: BrandColors.muted),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 10),
                  ],
                const SizedBox(height: 24),
                Wrap(
                  alignment: WrapAlignment.center,
                  children: [
                    TextButton(
                      onPressed: () => _openLegal('privacy', 'سياسة الخصوصية'),
                      child: const Text('الخصوصية'),
                    ),
                    TextButton(
                      onPressed: () => _openLegal('terms', 'شروط الاستخدام'),
                      child: const Text('الشروط'),
                    ),
                  ],
                ),
              ],
            ),
          );
        },
      ),
    );
  }
}
