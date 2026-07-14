import 'package:flutter/material.dart';
import 'package:font_awesome_flutter/font_awesome_flutter.dart';
import 'package:get/get.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../core/config/env.dart';
import '../../../data/repositories/public_repository.dart';

/// أزرار الدخول الاجتماعي — تُعرض **للمزوّدين المفعّلين فقط** (تُجلب من الخادم)،
/// بالأيقونات لا الأسماء. العودة تُعالَج عبر DeepLinkService (ksgrowth://auth/social).
class SocialLoginButtons extends StatefulWidget {
  const SocialLoginButtons({super.key});

  @override
  State<SocialLoginButtons> createState() => _SocialLoginButtonsState();
}

class _SocialLoginButtonsState extends State<SocialLoginButtons> {
  final _providers = <String>[].obs;

  static const _icons = <String, IconData>{
    'google': FontAwesomeIcons.google,
    'facebook': FontAwesomeIcons.facebookF,
    'twitter': FontAwesomeIcons.xTwitter,
    'linkedin': FontAwesomeIcons.linkedinIn,
  };

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    try {
      final list = await Get.find<PublicRepository>().socialProviders();
      _providers.assignAll(list.where(_icons.containsKey));
    } catch (_) {
      // فشل الجلب → لا نعرض أزراراً (الدخول بالبريد يعمل).
    }
  }

  Future<void> _open(String provider) async {
    final uri = Uri.parse('${Env.apiBaseUrl}/auth/social/$provider/redirect');
    try {
      await launchUrl(uri, mode: LaunchMode.externalApplication);
    } catch (_) {}
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Obx(() {
      if (_providers.isEmpty) return const SizedBox.shrink();
      return Column(
        children: [
          const SizedBox(height: 20),
          Row(
            children: [
              const Expanded(child: Divider()),
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: 12),
                child: Text('أو تابع عبر', style: theme.textTheme.bodySmall),
              ),
              const Expanded(child: Divider()),
            ],
          ),
          const SizedBox(height: 14),
          Wrap(
            alignment: WrapAlignment.center,
            spacing: 14,
            runSpacing: 12,
            children: _providers.map((p) => _iconButton(theme, p)).toList(),
          ),
        ],
      );
    });
  }

  Widget _iconButton(ThemeData theme, String provider) {
    return Material(
      shape: CircleBorder(
          side: BorderSide(color: theme.colorScheme.outlineVariant)),
      color: theme.colorScheme.surface,
      child: InkWell(
        customBorder: const CircleBorder(),
        onTap: () => _open(provider),
        child: Padding(
          padding: const EdgeInsets.all(12),
          child: FaIcon(_icons[provider],
              size: 22, color: theme.colorScheme.onSurface),
        ),
      ),
    );
  }
}
