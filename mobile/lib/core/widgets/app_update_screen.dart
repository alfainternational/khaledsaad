import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

import '../api/app_update_gate.dart';

/// الشاشة التي تحلّ محلّ التطبيق كلّه حين يرفض الخادم هذه النسخة.
///
/// حاجبة بلا مخرج بقصد: العقد تجاوز النسخة، فكل نداء لاحق يعود ٤٢٦. ترك
/// المستخدم يتنقّل بين شاشات تفشل واحدةً واحدة يجعله يظنّ العطل في حسابه أو
/// في اتصاله، لا في نسخة يملك حلّها بضغطة.
///
/// ولا زرّ «لاحقًا»: لا شيء يعمل بعده.
class AppUpdateScreen extends StatelessWidget {
  const AppUpdateScreen({super.key, required this.requirement});

  final AppUpdateRequirement requirement;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final url = requirement.downloadUrl;

    return Scaffold(
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(24),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 420),
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Icon(
                    Icons.system_update,
                    size: 56,
                    color: theme.colorScheme.primary,
                  ),
                  const SizedBox(height: 20),
                  Text(
                    'يلزم تحديث التطبيق',
                    textAlign: TextAlign.center,
                    style: theme.textTheme.headlineSmall,
                  ),
                  const SizedBox(height: 12),

                  // نصّ الخادم بحرفه: هو مصدر السبب، وإعادة صياغته في التطبيق
                  // تخلق رسالتين لحالة واحدة تتباعدان مع كل تغيير.
                  Text(
                    requirement.message,
                    textAlign: TextAlign.center,
                    style: theme.textTheme.bodyLarge,
                  ),

                  if (requirement.basis != null) ...[
                    const SizedBox(height: 8),
                    Text(
                      requirement.basis!,
                      textAlign: TextAlign.center,
                      style: theme.textTheme.bodySmall,
                    ),
                  ],

                  const SizedBox(height: 24),

                  if (url != null && url.isNotEmpty)
                    FilledButton.icon(
                      onPressed: () => _open(url),
                      icon: const Icon(Icons.download),
                      label: const Text('نزّل النسخة الجديدة'),
                    )
                  else
                    // رابطٌ غائب لا يُخترع: يُقال للمستخدم من أين يأخذها.
                    Text(
                      'نزّل النسخة الجديدة من الموقع، ثم ثبّتها فوق النسخة الحالية.',
                      textAlign: TextAlign.center,
                      style: theme.textTheme.bodyMedium,
                    ),

                  const SizedBox(height: 12),
                  Text(
                    'تثبيتها فوق النسخة الحالية يحفظ بياناتك — لا تحتاج حذف التطبيق.',
                    textAlign: TextAlign.center,
                    style: theme.textTheme.bodySmall,
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }

  Future<void> _open(String url) async {
    final uri = Uri.tryParse(url);

    if (uri == null) return;

    await launchUrl(uri, mode: LaunchMode.externalApplication);
  }
}
