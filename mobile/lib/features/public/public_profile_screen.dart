import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../core/theme/app_theme.dart';
import '../../core/widgets/adaptive_layout.dart';
import '../../core/widgets/common.dart';

class PublicProfileScreen extends StatelessWidget {
  const PublicProfileScreen({
    super.key,
    required this.brand,
    this.profilePdfUrl,
  });

  final Map<String, dynamic> brand;
  final String? profilePdfUrl;

  @override
  Widget build(BuildContext context) {
    final about = _strings(brand['about']);
    final experience = _maps(brand['experience']);
    final education = _maps(brand['education']);
    final credentials = _maps(brand['credentials']);
    final skills = _strings(brand['skills']);
    final services = _strings(brand['professional_services']);
    final contact = brand['contact'] is Map
        ? Map<String, dynamic>.from(brand['contact'] as Map)
        : const <String, dynamic>{};

    return AdaptivePage(
      family: AdaptivePageFamily.reading,
      padding: EdgeInsets.zero,
      child: ListView(
        key: const PageStorageKey('public-profile'),
        padding: const EdgeInsets.fromLTRB(16, 20, 16, 100),
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
                const Text(
                  'السيرة المهنية',
                  style: TextStyle(color: BrandColors.cyan, fontSize: 13),
                ),
                const SizedBox(height: 8),
                Text(
                  brand['name']?.toString() ?? 'خالد سعد',
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 30,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 8),
                Text(
                  brand['professional_headline']?.toString() ?? '',
                  style: const TextStyle(color: Color(0xFFD9E8FF), height: 1.6),
                ),
                const SizedBox(height: 8),
                Text(
                  '${brand['location'] ?? ''} · ${brand['experience_years'] ?? ''}',
                  style: const TextStyle(color: Color(0xFFB9D2FB)),
                ),
                if (contact['phone_display'] != null) ...[
                  const SizedBox(height: 8),
                  Text(
                    contact['phone_display'].toString(),
                    textDirection: TextDirection.ltr,
                    style: const TextStyle(color: Colors.white),
                  ),
                ],
                if (profilePdfUrl != null) ...[
                  const SizedBox(height: 14),
                  OutlinedButton.icon(
                    style: OutlinedButton.styleFrom(
                      foregroundColor: Colors.white,
                      side: const BorderSide(color: Color(0xFFB9D2FB)),
                    ),
                    onPressed: () => _openExternal(profilePdfUrl!),
                    icon: const Icon(Icons.picture_as_pdf_outlined),
                    label: const Text('تنزيل السيرة PDF'),
                  ),
                ],
              ],
            ),
          ),
          const SizedBox(height: 20),
          _Section(
            title: 'نبذة مهنية',
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                for (final paragraph in about) ...[
                  Text(paragraph, style: const TextStyle(height: 1.75)),
                  const SizedBox(height: 10),
                ],
              ],
            ),
          ),
          const SizedBox(height: 14),
          const Text(
            'الخبرات المهنية',
            style: TextStyle(fontSize: 21, fontWeight: FontWeight.w800),
          ),
          const SizedBox(height: 10),
          for (final job in experience) ...[
            BrandCard(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    job['role']?.toString() ?? '',
                    style: const TextStyle(
                      color: BrandColors.navy,
                      fontSize: 18,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  Text(
                    job['company']?.toString() ?? '',
                    style: const TextStyle(fontWeight: FontWeight.w700),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    '${job['period'] ?? ''} · ${job['location'] ?? ''}',
                    style: const TextStyle(color: BrandColors.muted),
                  ),
                  if (_strings(job['responsibilities']).isNotEmpty) ...[
                    const SizedBox(height: 10),
                    for (final responsibility in _strings(
                      job['responsibilities'],
                    ))
                      Padding(
                        padding: const EdgeInsets.only(bottom: 6),
                        child: Row(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            const Padding(
                              padding: EdgeInsets.only(top: 7),
                              child: Icon(
                                Icons.circle,
                                size: 6,
                                color: BrandColors.blue,
                              ),
                            ),
                            const SizedBox(width: 8),
                            Expanded(
                              child: Text(
                                responsibility,
                                style: const TextStyle(height: 1.6),
                              ),
                            ),
                          ],
                        ),
                      ),
                  ],
                ],
              ),
            ),
            const SizedBox(height: 10),
          ],
          _Section(
            title: 'التعليم',
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                for (final item in education) ...[
                  Text(
                    item['degree']?.toString() ?? '',
                    style: const TextStyle(fontWeight: FontWeight.w800),
                  ),
                  Text(item['institution']?.toString() ?? ''),
                  Text(
                    item['period']?.toString() ?? '',
                    style: const TextStyle(color: BrandColors.muted),
                  ),
                ],
              ],
            ),
          ),
          const SizedBox(height: 14),
          _CredentialSection(items: credentials),
          const SizedBox(height: 14),
          _ChipSection(title: 'الخدمات المهنية', items: services),
          const SizedBox(height: 14),
          _ChipSection(title: 'المهارات', items: skills),
          if ([
            contact['whatsapp'],
            contact['linkedin'],
            contact['x'],
          ].any((value) => value != null)) ...[
            const SizedBox(height: 14),
            _Section(
              title: 'روابط التواصل',
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  if (contact['whatsapp'] != null)
                    FilledButton.icon(
                      onPressed: () =>
                          _openExternal(contact['whatsapp'].toString()),
                      icon: const Icon(Icons.chat_outlined),
                      label: const Text('WhatsApp'),
                    ),
                  if (contact['linkedin'] != null) ...[
                    const SizedBox(height: 8),
                    OutlinedButton.icon(
                      onPressed: () =>
                          _openExternal(contact['linkedin'].toString()),
                      icon: const Icon(Icons.work_outline),
                      label: const Text('LinkedIn'),
                    ),
                  ],
                  if (contact['x'] != null) ...[
                    const SizedBox(height: 8),
                    OutlinedButton.icon(
                      onPressed: () => _openExternal(contact['x'].toString()),
                      icon: const Icon(Icons.alternate_email),
                      label: const Text('X / Twitter'),
                    ),
                  ],
                ],
              ),
            ),
          ],
        ],
      ),
    );
  }
}

class _Section extends StatelessWidget {
  const _Section({required this.title, required this.child});

  final String title;
  final Widget child;

  @override
  Widget build(BuildContext context) => BrandCard(
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          title,
          style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w800),
        ),
        const SizedBox(height: 12),
        child,
      ],
    ),
  );
}

class _ChipSection extends StatelessWidget {
  const _ChipSection({required this.title, required this.items});

  final String title;
  final List<String> items;

  @override
  Widget build(BuildContext context) => _Section(
    title: title,
    child: Wrap(
      spacing: 7,
      runSpacing: 7,
      children: [for (final item in items) Chip(label: Text(item))],
    ),
  );
}

class _CredentialSection extends StatelessWidget {
  const _CredentialSection({required this.items});

  final List<Map<String, dynamic>> items;

  @override
  Widget build(BuildContext context) => _Section(
    title: 'الشهادات والتراخيص',
    child: Column(
      children: [
        for (final item in items)
          Padding(
            padding: const EdgeInsets.only(bottom: 10),
            child: Container(
              width: double.infinity,
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: BrandColors.surfaceSoft,
                borderRadius: BorderRadius.circular(14),
                border: Border.all(color: BrandColors.line),
              ),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Icon(Icons.verified_outlined, color: BrandColors.blue),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          item['name']?.toString() ?? '',
                          style: const TextStyle(
                            color: BrandColors.navy,
                            fontWeight: FontWeight.w800,
                            height: 1.5,
                          ),
                        ),
                        if (_credentialMeta(item).isNotEmpty) ...[
                          const SizedBox(height: 3),
                          Text(
                            _credentialMeta(item),
                            style: const TextStyle(color: BrandColors.muted),
                          ),
                        ],
                        if (item['credential_id'] != null) ...[
                          const SizedBox(height: 3),
                          Text(
                            'مُعرّف الاعتماد: ${item['credential_id']}',
                            textDirection: TextDirection.rtl,
                            style: const TextStyle(
                              color: BrandColors.muted,
                              fontSize: 12,
                            ),
                          ),
                        ],
                        if (item['url'] != null) ...[
                          const SizedBox(height: 4),
                          TextButton.icon(
                            style: TextButton.styleFrom(
                              padding: EdgeInsets.zero,
                              tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                            ),
                            onPressed: () =>
                                _openExternal(item['url'].toString()),
                            icon: const Icon(Icons.open_in_new, size: 16),
                            label: const Text('عرض الاعتماد'),
                          ),
                        ],
                      ],
                    ),
                  ),
                ],
              ),
            ),
          ),
      ],
    ),
  );
}

String _credentialMeta(Map<String, dynamic> item) => [
  item['issuer']?.toString(),
  item['issued']?.toString(),
].whereType<String>().where((value) => value.isNotEmpty).join(' · ');

List<Map<String, dynamic>> _maps(dynamic value) => (value as List? ?? const [])
    .whereType<Map>()
    .map((item) => Map<String, dynamic>.from(item))
    .toList();

List<String> _strings(dynamic value) =>
    (value as List? ?? const []).map((item) => item.toString()).toList();

Future<void> _openExternal(String value) async {
  final uri = Uri.tryParse(value);
  if (uri == null) return;
  await launchUrl(uri, mode: LaunchMode.externalApplication);
}
