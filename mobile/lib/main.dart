import 'dart:async';

import 'package:app_links/app_links.dart';
import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';

import 'core/api/api_client.dart';
import 'core/api/app_update_gate.dart';
import 'core/api/platform_repository.dart';
import 'core/firebase/firebase_service.dart';
import 'core/theme/app_theme.dart';
import 'features/auth/auth_screen.dart';
import 'features/auth/password_reset_screen.dart';
import 'core/widgets/app_update_screen.dart';
import 'features/projects/dashboard_screen.dart';
import 'features/public/public_home_screen.dart';
import 'features/public/shared_report_screen.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();

  final repository = PlatformRepository(ApiClient());

  // تهيئة Firebase (إشعارات/تحليلات) لا تُسقط فتح التطبيق أبدًا: هي خدمة جانبية
  // لا شرط لبدء التطبيق. أي فشل فيها يُبتلع ليفتح التطبيق على كل حال.
  try {
    await FirebaseService.instance.initialize();
  } catch (error, stack) {
    debugPrint('Firebase init failed (non-fatal): $error\n$stack');
  }

  runApp(KhaledSaadApp(repository: repository));
}

class KhaledSaadApp extends StatefulWidget {
  const KhaledSaadApp({super.key, required this.repository});

  final PlatformRepository repository;

  @override
  State<KhaledSaadApp> createState() => _KhaledSaadAppState();
}

class _KhaledSaadAppState extends State<KhaledSaadApp> {
  final _navigatorKey = GlobalKey<NavigatorState>();
  final _appLinks = AppLinks();
  StreamSubscription<Uri>? _linkSubscription;
  late Future<bool> _session = _restore();
  bool _showAuth = false;
  bool _registering = true;

  @override
  void initState() {
    super.initState();
    unawaited(_listenForLinks());
  }

  @override
  void dispose() {
    _linkSubscription?.cancel();
    super.dispose();
  }

  Future<void> _listenForLinks() async {
    final initial = await _appLinks.getInitialLink();
    if (initial != null) _openLink(initial);
    _linkSubscription = _appLinks.uriLinkStream.listen(_openLink);
  }

  void _openLink(Uri uri) {
    final segments = [
      if (uri.scheme == 'khaledsaad' && uri.host.isNotEmpty) uri.host,
      ...uri.pathSegments,
    ];
    WidgetsBinding.instance.addPostFrameCallback((_) {
      final navigator = _navigatorKey.currentState;
      if (navigator == null || segments.isEmpty) return;

      if (segments.first == 'r' && segments.length >= 2) {
        navigator.push(
          MaterialPageRoute(
            builder: (_) => SharedReportScreen(
              repository: widget.repository,
              token: segments[1],
            ),
          ),
        );
      }

      if (segments.first == 'reset-password' && segments.length >= 2) {
        navigator.push(
          MaterialPageRoute(
            builder: (_) => PasswordResetScreen(
              repository: widget.repository,
              token: segments[1],
              email: uri.queryParameters['email'] ?? '',
              onComplete: () {
                navigator.pop();
                _openAuth(registering: false);
              },
            ),
          ),
        );
      }
    });
  }

  Future<bool> _restore() async {
    final token = await widget.repository.client.tokens.read();

    if (token == null) return false;

    try {
      await widget.repository.me();
      await FirebaseService.instance.syncDevice(widget.repository);
      return true;
    } catch (_) {
      // رمز منتهٍ: نبدأ من شاشة الدخول بدل شاشة بيضاء أو سبينر بلا نهاية.
      return false;
    }
  }

  Future<void> _setAuthenticated(bool value) async {
    if (value) {
      await FirebaseService.instance.syncDevice(widget.repository);
    }
    if (!mounted) return;
    setState(() {
      _showAuth = false;
      _session = Future.value(value);
    });
  }

  void _openAuth({required bool registering}) {
    setState(() {
      _registering = registering;
      _showAuth = true;
    });
  }

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      navigatorKey: _navigatorKey,
      title: 'خالد سعد',
      debugShowCheckedModeBanner: false,
      theme: AppTheme.build(),
      locale: const Locale('ar'),
      supportedLocales: const [Locale('ar')],
      localizationsDelegates: GlobalMaterialLocalizations.delegates,
      // الواجهة عربية RTL أولًا، تمامًا كما في الويب.
      //
      // وبوابة التحديث تلفّ كل شيء داخل `builder` لا داخل `home`: أي مسار
      // مفتوح — تقرير مشترك، إعادة كلمة مرور، شاشة عميقة — يعود منه ٤٢٦
      // كذلك، فالحارس فوق المسارات كلها لا فوق أولها.
      builder: (context, child) => Directionality(
        textDirection: TextDirection.rtl,
        child: ValueListenableBuilder<AppUpdateRequirement?>(
          valueListenable: AppUpdateGate.instance.requirement,
          builder: (context, requirement, _) => requirement == null
              ? (child ?? const SizedBox.shrink())
              : AppUpdateScreen(requirement: requirement),
        ),
      ),
      home: FutureBuilder<bool>(
        future: _session,
        builder: (context, snapshot) {
          if (snapshot.connectionState == ConnectionState.waiting) {
            return const Scaffold(
              body: Center(child: CircularProgressIndicator()),
            );
          }

          if (snapshot.data == true) {
            return DashboardScreen(
              repository: widget.repository,
              onLogout: () => _setAuthenticated(false),
            );
          }

          if (_showAuth) {
            return AuthScreen(
              repository: widget.repository,
              registering: _registering,
              onBack: () => setState(() => _showAuth = false),
              onAuthenticated: () => _setAuthenticated(true),
            );
          }

          return PublicHomeScreen(
            repository: widget.repository,
            onLogin: () => _openAuth(registering: false),
            onRegister: () => _openAuth(registering: true),
          );
        },
      ),
    );
  }
}
