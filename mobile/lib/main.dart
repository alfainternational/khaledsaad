import 'package:flutter/material.dart';

import 'core/api/api_client.dart';
import 'core/api/platform_repository.dart';
import 'core/theme/app_theme.dart';
import 'features/auth/auth_screen.dart';
import 'features/projects/dashboard_screen.dart';

void main() {
  runApp(KhaledSaadApp(repository: PlatformRepository(ApiClient())));
}

class KhaledSaadApp extends StatefulWidget {
  const KhaledSaadApp({super.key, required this.repository});

  final PlatformRepository repository;

  @override
  State<KhaledSaadApp> createState() => _KhaledSaadAppState();
}

class _KhaledSaadAppState extends State<KhaledSaadApp> {
  late Future<bool> _session = _restore();

  Future<bool> _restore() async {
    final token = await widget.repository.client.tokens.read();

    if (token == null) return false;

    try {
      await widget.repository.me();
      return true;
    } catch (_) {
      // رمز منتهٍ: نبدأ من شاشة الدخول بدل شاشة بيضاء أو سبينر بلا نهاية.
      return false;
    }
  }

  void _setAuthenticated(bool value) {
    setState(() => _session = Future.value(value));
  }

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'خالد سعد',
      debugShowCheckedModeBanner: false,
      theme: AppTheme.build(),
      locale: const Locale('ar'),
      // الواجهة عربية RTL أولًا، تمامًا كما في الويب.
      builder: (context, child) => Directionality(
        textDirection: TextDirection.rtl,
        child: child ?? const SizedBox.shrink(),
      ),
      home: FutureBuilder<bool>(
        future: _session,
        builder: (context, snapshot) {
          if (snapshot.connectionState == ConnectionState.waiting) {
            return const Scaffold(body: Center(child: CircularProgressIndicator()));
          }

          return snapshot.data == true
              ? DashboardScreen(
                  repository: widget.repository,
                  onLogout: () => _setAuthenticated(false),
                )
              : AuthScreen(
                  repository: widget.repository,
                  onAuthenticated: () => _setAuthenticated(true),
                );
        },
      ),
    );
  }
}
