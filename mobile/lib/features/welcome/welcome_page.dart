import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:get_storage/get_storage.dart';

import '../../app/routes/app_routes.dart';
import '../../app/theme/app_colors.dart';

class WelcomePage extends StatelessWidget {
  const WelcomePage({super.key});

  static const _seenKey = 'welcome_seen';

  Future<void> _go(String route) async {
    await GetStorage().write(_seenKey, true);
    Get.offAllNamed(route);
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Scaffold(
      bottomNavigationBar: SafeArea(
        minimum: const EdgeInsets.fromLTRB(20, 8, 20, 20),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            FilledButton.icon(
              onPressed: () => _go(Routes.register),
              icon: const Icon(Icons.arrow_back),
              label: const Text('ابدأ الآن'),
            ),
            const SizedBox(height: 10),
            OutlinedButton(
              onPressed: () => _go(Routes.login),
              child: const Text('تسجيل الدخول'),
            ),
          ],
        ),
      ),
      body: SafeArea(
        child: ListView(
          padding: const EdgeInsets.fromLTRB(20, 24, 20, 28),
          children: [
            const _BrandMark(size: 64),
            const SizedBox(height: 28),
            Text(
              'منصة تزيد وضوح التسويق وتنقله للتنفيذ',
              style: theme.textTheme.headlineSmall?.copyWith(
                fontWeight: FontWeight.w900,
                height: 1.35,
              ),
            ),
            const SizedBox(height: 12),
            Text(
              'ابدأ بمشروعك، اعرف الخلل الحقيقي، ثم حوّل التشخيص إلى أدوات وقرارات ومخرجات قابلة للقياس.',
              style: theme.textTheme.bodyLarge?.copyWith(
                height: 1.7,
                color: theme.colorScheme.onSurfaceVariant,
              ),
            ),
            const SizedBox(height: 24),
            const _BenefitTile(
              icon: Icons.manage_search_outlined,
              title: 'تشخيص قبل الصرف',
              body: 'اعرف هل المشكلة في الوصول، الثقة، العرض، أو التحويل.',
            ),
            const _BenefitTile(
              icon: Icons.route_outlined,
              title: 'خطوة تالية واضحة',
              body: 'الأدوات تظهر حسب ما يحتاجه المشروع تالياً.',
            ),
            const _BenefitTile(
              icon: Icons.auto_awesome_outlined,
              title: 'تنفيذ قابل للقياس',
              body: 'حوّل التوصيات إلى محتوى، صفحات، حملات، ومؤشرات أداء.',
            ),
          ],
        ),
      ),
    );
  }
}

class _BenefitTile extends StatelessWidget {
  const _BenefitTile({
    required this.icon,
    required this.title,
    required this.body,
  });

  final IconData icon;
  final String title;
  final String body;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Card(
      margin: const EdgeInsets.only(bottom: 10),
      child: ListTile(
        leading: Icon(icon, color: theme.colorScheme.primary),
        title: Text(
          title,
          style: theme.textTheme.titleSmall?.copyWith(
            fontWeight: FontWeight.w800,
          ),
        ),
        subtitle: Text(body),
      ),
    );
  }
}

class _BrandMark extends StatelessWidget {
  const _BrandMark({required this.size});

  final double size;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: size,
      height: size,
      alignment: Alignment.center,
      decoration: BoxDecoration(
        color: AppColors.primary,
        borderRadius: BorderRadius.circular(18),
      ),
      child: Text(
        'KS',
        style: Theme.of(context).textTheme.titleLarge?.copyWith(
          color: Colors.white,
          fontWeight: FontWeight.w900,
        ),
      ),
    );
  }
}
