import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';
import 'package:get/get.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../core/config/env.dart';
import '../../../data/repositories/public_repository.dart';

/// أزرار الدخول الاجتماعي — تُعرض **للمزوّدين المفعّلين فقط** (تُجلب من الخادم)،
/// بالأيقونات لا الأسماء. تخدم التسجيل والدخول معاً؛ العودة عبر DeepLinkService.
class SocialLoginButtons extends StatefulWidget {
  const SocialLoginButtons({super.key});

  @override
  State<SocialLoginButtons> createState() => _SocialLoginButtonsState();
}

class _SocialLoginButtonsState extends State<SocialLoginButtons> {
  final _providers = <String>[].obs;

  /// أيقونات البراند (مسار SVG أحادي، viewBox 24) — أحادية اللون تُلوَّن بالثيم.
  static const _svgPaths = <String, String>{
    'google':
        'M12.48 10.92v3.28h7.84c-.24 1.84-.853 3.187-1.787 4.133-1.147 1.147-2.933 2.4-6.053 2.4-4.827 0-8.6-3.893-8.6-8.72s3.773-8.72 8.6-8.72c2.6 0 4.507 1.027 5.907 2.347l2.307-2.307C18.747 1.44 16.133 0 12.48 0 5.867 0 .307 5.387.307 12s5.56 12 12.173 12c3.573 0 6.267-1.173 8.373-3.36 2.16-2.16 2.84-5.213 2.84-7.667 0-.76-.053-1.467-.173-2.053H12.48z',
    'facebook':
        'M9.101 23.691v-7.98H6.627v-3.667h2.474v-1.58c0-4.085 1.848-5.978 5.858-5.978.401 0 .955.042 1.468.103a8.68 8.68 0 0 1 1.141.195v3.325a8.623 8.623 0 0 0-.653-.036 26.805 26.805 0 0 0-.733-.009c-.707 0-1.259.096-1.675.309a1.686 1.686 0 0 0-.679.622c-.258.42-.374.995-.374 1.752v1.297h3.919l-.386 2.103-.287 1.564h-3.246v8.245C19.396 23.238 24 18.179 24 12.044c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.628 3.874 10.35 9.101 11.647z',
    'twitter':
        'M18.901 1.153h3.68l-8.04 9.19L24 22.846h-7.406l-5.8-7.584-6.638 7.584H.474l8.6-9.83L0 1.154h7.594l5.243 6.932ZM17.61 20.644h2.039L6.486 3.24H4.298Z',
    'linkedin':
        'M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 01-2.063-2.065 2.064 2.064 0 112.063 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z',
  };

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    try {
      final list = await Get.find<PublicRepository>().socialProviders();
      _providers.assignAll(list.where(_svgPaths.containsKey));
    } catch (_) {
      // فشل الجلب → لا نعرض أزراراً (الدخول/التسجيل بالبريد يعمل).
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
    final svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">'
        '<path d="${_svgPaths[provider]}"/></svg>';
    return Material(
      shape: CircleBorder(
          side: BorderSide(color: theme.colorScheme.outlineVariant)),
      color: theme.colorScheme.surface,
      child: InkWell(
        customBorder: const CircleBorder(),
        onTap: () => _open(provider),
        child: Padding(
          padding: const EdgeInsets.all(13),
          child: SvgPicture.string(
            svg,
            width: 22,
            height: 22,
            colorFilter: ColorFilter.mode(
                theme.colorScheme.onSurface, BlendMode.srcIn),
          ),
        ),
      ),
    );
  }
}
