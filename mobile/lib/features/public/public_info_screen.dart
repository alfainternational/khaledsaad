import 'package:flutter/material.dart';

import '../../core/theme/app_theme.dart';
import '../../core/widgets/adaptive_layout.dart';
import '../../core/widgets/common.dart';

class PublicInfoScreen extends StatelessWidget {
  const PublicInfoScreen({super.key, required this.brand});

  final Map<String, dynamic> brand;

  @override
  Widget build(BuildContext context) {
    final problems = _maps(brand['problems']);
    final services = _maps(brand['services']);
    final method = _maps(brand['method']);
    final principles = _maps(brand['principles']);
    final faqs = _maps(brand['faqs']);
    final contact = brand['contact'] is Map
        ? Map<String, dynamic>.from(brand['contact'] as Map)
        : const <String, dynamic>{};

    return AdaptivePage(
      family: AdaptivePageFamily.operational,
      padding: EdgeInsets.zero,
      child: ListView(
        key: const PageStorageKey('public-info'),
        padding: const EdgeInsets.fromLTRB(16, 20, 16, 100),
        children: [
          const Eyebrow('كل التفاصيل'),
          const SizedBox(height: 6),
          const Text(
            'الخدمات والمنهجية والأسئلة',
            style: TextStyle(fontSize: 24, fontWeight: FontWeight.w800),
          ),
          const SizedBox(height: 16),
          _InfoGroup(
            icon: Icons.troubleshoot,
            title: 'المشكلات التي نبدأ منها',
            items: problems,
          ),
          _InfoGroup(
            icon: Icons.flag_outlined,
            title: 'ما تخرج به',
            items: services,
          ),
          _InfoGroup(
            icon: Icons.route_outlined,
            title: 'منهجية العمل',
            items: method,
          ),
          _InfoGroup(
            icon: Icons.verified_outlined,
            title: 'مبادئ العمل',
            items: principles,
          ),
          BrandCard(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text(
                  'الأسئلة الشائعة',
                  style: TextStyle(fontSize: 19, fontWeight: FontWeight.w800),
                ),
                const SizedBox(height: 8),
                for (final faq in faqs)
                  ExpansionTile(
                    tilePadding: EdgeInsets.zero,
                    title: Text(faq['question']?.toString() ?? ''),
                    children: [
                      Padding(
                        padding: const EdgeInsets.only(bottom: 14),
                        child: Text(
                          faq['answer']?.toString() ?? '',
                          style: const TextStyle(
                            color: BrandColors.muted,
                            height: 1.65,
                          ),
                        ),
                      ),
                    ],
                  ),
              ],
            ),
          ),
          const SizedBox(height: 14),
          BrandCard(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text(
                  'التواصل',
                  style: TextStyle(fontSize: 19, fontWeight: FontWeight.w800),
                ),
                const SizedBox(height: 8),
                Text(brand['location']?.toString() ?? ''),
                if (contact['phone_display'] != null)
                  Text(
                    contact['phone_display'].toString(),
                    textDirection: TextDirection.ltr,
                  ),
                const SizedBox(height: 8),
                const Text(
                  'تجد روابط WhatsApp وLinkedIn وX في صفحة السيرة المهنية.',
                  style: TextStyle(color: BrandColors.muted),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _InfoGroup extends StatelessWidget {
  const _InfoGroup({
    required this.icon,
    required this.title,
    required this.items,
  });

  final IconData icon;
  final String title;
  final List<Map<String, dynamic>> items;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(bottom: 14),
    child: BrandCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(icon, color: BrandColors.blue),
              const SizedBox(width: 9),
              Expanded(
                child: Text(
                  title,
                  style: const TextStyle(
                    fontSize: 19,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          for (final item in items) ...[
            Text(
              item['title']?.toString() ?? '',
              style: const TextStyle(
                color: BrandColors.navy,
                fontWeight: FontWeight.w800,
              ),
            ),
            const SizedBox(height: 3),
            Text(
              item['description']?.toString() ?? '',
              style: const TextStyle(color: BrandColors.muted, height: 1.55),
            ),
            const Divider(height: 24),
          ],
        ],
      ),
    ),
  );
}

List<Map<String, dynamic>> _maps(dynamic value) => (value as List? ?? const [])
    .whereType<Map>()
    .map((item) => Map<String, dynamic>.from(item))
    .toList();
